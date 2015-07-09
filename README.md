# DynamicModel
A PHP Model for use on any project.

Usage:
* Include the *Model.php*
* Create an object
	` $table1 = new Model(['host'=>'localhost','username'=>'root','password'=>'','db'=>'test']); `
* Table list is automatically filled and saved in the object.
* Set te table this object is for
	` $table1->setTable('table1'); `
* Use the following as required
	* `$table1->findByPK(int $pk [, array $fields]); `
	* `$table1->findAll([
		array [ 'fields'=>string "", 'order_by'=>string "col1 ASC", 'where'=>string 'col1 = 1 AND col2 = 2' ]
		])`
	* `$table1->insert(array [
		"col1"=>"",
		"col2"=>"", ...
	]);`
	* `$table1->update(
		array [ "col1" => 2 ],array ['col2' => 4 ]
	);`
