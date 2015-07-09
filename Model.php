<?php
    class Model {
        private $columns;
        private $types;
        private $tables;
        private $cur_table;
        private $pk;
        private $_db;
        private $_pk_value;
        
        public function __construct($db){
            $this->_db = new \mysqli($db['host'],$db['user'],$db['pass'],$db['db']);
            $this->init();
        }
        
        public function setTable($table){
            if(in_array($table,$this->tables)){
                $this->cur_table = $table;
                $this->setColumns();
            }else{
                throw new \Exception("Table not found");
            }
        }
        
        private function setColumns(){
            $query = $this->_db->query("SHOW COLUMNS FROM ".$this->cur_table);
            while($data = $query->fetch_array()){
                $this->columns[] = [
                                    "name"=>$data['Field'],
                                    "key"=>$data['Key'],
                                    "required"=>(($data['Null'] == "YES")?true:false),
                                    "type"=> $data['Type']
                                ];
            }
            $this->setPK();
        }
        
        private function setPK(){
            foreach($this->columns as $col){
                if(strtolower($col['key']) == "pri"){
                    $this->pk = $col['name'];
                }
            }
        }
        
        private function init(){
            $query = $this->_db->query("SHOW TABLES");
            while($data = $query->fetch_array()){
                $this->tables[] = $data[0];
            }
        }
        
        public function findAll(array $conditions){
            $columns = isset($conditions['fields'])?$conditions['fields']:"*";
            $order = isset($conditions['order_by'])?$conditions['order_by']:$this->pk." ASC";
            $condition = isset($conditions['where'])?$conditions['where']:"1";
            $query = "SELECT %s FROM `%s` WHERE %s ORDER BY %s";
            if(!in_array($columns,$this->getColNames()) && $columns != "*"){
                throw new \Exception("Column Not found");
            }
            $array = false;
            if(is_array($columns)){
                $array = true;
                $columns = implode(",",$columns);
            }
            $query = sprintf($query,$columns,$this->cur_table,$condition,$order);
            $stmt = $this->_db->prepare($query) or die($this->_db->error);
            $stmt->execute();
            return $this->columnstoFetch($stmt,$array?explode(",",$columns):$columns);
        }
        
        public function findByPK($id, $cols = "*"){
            $array = false;
            if(is_array($cols)){
                $array = true;
                $cols = implode(",",$cols);
            }
            $query = sprintf("SELECT %s FROM `%s` WHERE `%s` = ?",$cols,$this->cur_table,$this->pk);
            $res = $this->_db->prepare($query) or die($this->_db->error);
            $res->bind_param("i",$id);
            $res->execute();
            return $this->columnstoFetch($res, $array?explode(",",$cols):$cols)[0];
        }
        
        public function insert(array $data){
            $query = "INSERT INTO %s(%s) VALUES(%s)";
            foreach($data as $feild => $dat){
                if(in_array($feild,$this->getColNames())){
                    $cols[] = $feild;
                    $place[] = "?";
                }else{
                    throw new \Exception("Column ".$feild." Not found");
                }
            }
            $cols = implode(",",$cols);
            $place = implode(",",$place);
            $query = sprintf($query,$this->cur_table,$cols,$place);
            $stmt = $this->_db->prepare($query) or die($this->_db->error);
            $dataTypes = $this->dataTypesforInsert(explode(",",$cols));
            $stmt = $this->dynamicInsert($stmt,$dataTypes,$data);
            if(!$stmt->execute()){
                new \Exception("There was an error. ".$this->_db->error);
            }else{
                $this->_pk_value = $this->_db->insert_id;
                return true;
            }
        }
        
        public function update(array $data,array $condition){
            $query = "UPDATE %s SET %s WHERE %s";
            foreach($data as $feild => $dat){
                if(in_array($feild,$this->getColNames())){
                    $updates[] = $feild." = ?";
                    $cols[] = $feild;
                }else{
                    throw new \Exception("Column ".$feild." Not found");
                }
            }
            $update = implode(",",$updates);
            foreach($condition as $feild => $value){
                if(in_array($feild,$this->getColNames())){
                    $conditions[] = $feild." = ?";
                    $cols[] = $feild;
                }else{
                    throw new \Exception("Column ".$feild." Not found");
                }
            }
            $conditions = implode(" AND ",$conditions);
            $query = sprintf($query,$this->cur_table,$update,$conditions);
            $stmt = $this->_db->prepare($query);
            $ph = $this->dataTypesforInsert($cols);
            $stmt = $this->dynamicUpdate($stmt,$ph, array_merge($data,$condition));
            if(!$stmt->execute()){
                new \Exception("There was an error. ".$this->_db->error);
            }else{
                return true;
            }
            
        }
        
        private function columnstoFetch($stmt, $cols){
            $meta = $stmt->result_metadata();
            while ($field = $meta->fetch_field()) {
                if(is_array($cols)){
                    if(in_array($field->name,$cols)){
                        $parameters[] = &$row[$field->name];
                    }
                }else{
                    $parameters[] = &$row[$field->name];
                }
            }
            call_user_func_array(array($stmt, 'bind_result'), $parameters);
            while ($stmt->fetch()) {
                foreach($row as $key => $val) {
                    $x[$key] = $val;
                }
                $results[] = $x;
            }
            return $results;
        }
        
        private function getColNames(){
            foreach($this->columns as $cols){
                $name[] = $cols['name'];
            }
            return $name;
        }
        
        private function dataTypesforInsert(array $cols){
            $types = "";
            foreach($cols as $col){
                foreach($this->columns as $coln){
                    if($col == $coln['name']){
                        $types .= $this->getDataType($col);
                    }
                }
            }
            return ["types"=> $types];
        }
        
        private function dynamicInsert($stmt, $dataph, $data){
            $array = array_merge($dataph,$data);
            if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
            {
                $refs = array();
                foreach($array as $key => $value)
                    $refs[$key] = &$array[$key];
            }
            call_user_func_array([$stmt,"bind_param"],$refs);
            return $stmt;
        }
        
        private function dynamicUpdate($stmt, $dataph, $data){
            $array = array_merge($dataph,$data);
            if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
            {
                $refs = array();
                foreach($array as $key => $value)
                    $refs[$key] = &$array[$key];
            }
            call_user_func_array([$stmt,"bind_param"],$refs);
            return $stmt;
        }
        
        private function getDataType($column){
            foreach($this->columns as $col){
                if($col["name"] == $column){
                    $type = $col['type'];
                    break;
                }
            }
            if(preg_match("/(char)|(varchar)|(binary)|(TEXT)|(ENUM)|(SET)/i",$type)){
                return "s";
            }elseif(preg_match("/(blob)/i",$type)){
                return "b";
            }elseif(preg_match("/(bit)|(int)|(bool)/i",$type)){
                return "i";
            }elseif(preg_match("/(float)|(decimal)|(double)/i",$type)){
                return "d";
            }elseif(preg_match("/(data)|(time)|(year)/",$type)){
                return "s";
            }
        }
        
        public function getLastId(){
            return $this->_pk_value;
        }
        
        public function now(){
            return date('Y-m-d H:i:s');
        }
    }
    