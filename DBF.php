<?php
/**
 * 
 * Class to copy DBF tables to SQL database (tested on MySQL)
 * @author Jaroslav
 *
 */
class Helper_DBF{
	
	/**
	 * File from which to read data
	 * @var string
	 */
	public $databaseFile = '';
	
	// 
	/**
	 * Table name where we will save retrieved data. 
	 * Will be automatically generated if left empty.
	 * @var string
	 */
	public $table = '';
	
	/**
	 * Table name prefix
	 * @var string
	 */
	public $tablePrefix = 'dbf_';
	
	/**
	 * Function to call on every row. 
	 * Please read more about it here: http://www.php.net/manual/en/function.call-user-func.php
	 * @var mixed
	 */
	public $callbackInsert = '';
	
	/**
	 * Function quote data to be inserted in database. 
	 * Please read more about it here: http://www.php.net/manual/en/function.call-user-func.php
	 * @var mixed
	 */
	public $callbackQuote = 'mysql_real_escape_string';
	
	/**
	* Encoding of data it DBF table
	* @var string
	*/
	public $encodingIn = 'Windows-1257';
	
	/**
	* Encoding of output data
	* @var string
	*/
	public $encodingOut = 'UTF-8//TRANSLIT';
		
	
	
	private $db = false;
	
	
	/**
	 * Class constructor to set all variables and open database file.
	 * 
	 * @param string $databaseFile
	 * @param string $table
	 * @param string $tablePrefix
	 */
	public function __construct($databaseFile, $table = ''){
		$this->databaseFile = $databaseFile;
		$this->table = $table;
		
		if (empty($this->table)){
			$this->table = $this->defaultTableName();
		}
		
		$this->openDB();
	}
	
	/**
	 * Class destructor to close the database file
	 */
	public function __destruct(){
		$this->closeDB();
	}
	
	/**
	 * Returns the name of database file without extension, to be used as default name for table
	 */
	public function defaultTableName(){
		$file = explode('/', $this->databaseFile);
		$fileName = explode('.', $file[count($file)-1]);
		return $fileName[0];
	}
	
	
	/**
	 * Prepares queries to create new table for import
	 */
	public function createTable(){
		$db = &$this->db;
		$record_numbers = dbase_numrecords($db);
		if ($record_numbers < 1){
			echo 'No entries found. Skipping';		// You can delete this line safely if you don't need it
			return false;
		}		 
		
		$column_info = dbase_get_header_info($db);
		 
		$i = 0 ;
		$createTable = 'CREATE TABLE `'.$this->tablePrefix.$this->table.'` (';
		foreach($column_info as $col){
			if (++$i > 1) $createTable .= ', ';
			$createTable .= '`'.$col['name'].'` ';
			switch($col['type']){
				case 'character':
					$createTable .= ' VARCHAR('.$col['length'].') ';
					break;
				case 'boolean':
					$createTable .= ' INT(1) ';
					break;
				case 'number':
					$createTable .= ' DECIMAL('.$col['length'].', '.$col['precision'].') ';
					break;
				default:
					$createTable .= ' TEXT ';
			}
		}
		$createTable .= ');';

		return $createTable;
	}
	
	/**
	 * Delete table to create new one
	 */
	public function deleteTable(){
		$deleteTable = 'DROP TABLE IF EXISTS `'.$this->tablePrefix.$this->table.'` ';
		return $deleteTable;
	}
	
	/**
	 * Read data from DBF file and give it back to callbackInsert function (where you can write it to your database)
	 */
	public function readData(){
		$db = &$this->db;
		$record_numbers = dbase_numrecords($db);
		
		for ($i = 1; $i <= $record_numbers; $i++) {
			$row = dbase_get_record_with_names($db, $i);
			if($row['deleted']) continue;  					// Skip deleted rows
			unset($row['deleted']);							// Don't save this field to our new table
		
			foreach ($row as $k => $v){						// Change encoding of table to something more useful
				$row[$k] = iconv($this->encodingIn, $this->encodingOut, $row[$k]);
			}
			
			$query = $this->insertStatement($this->tablePrefix.$this->table, $row);
			call_user_func($this->callbackInsert, $query);
		}
	}
	
	/**
	 * Returns insert statement for a given row
	 * 
	 * @param string $table
	 * @param array $what
	 */
	protected function insertStatement($table, array $what){
		$vals = $this->prepareInsert($what);
		$sql = "INSERT INTO ".$table." (".$vals['a'].") VALUES (".$vals['b'].") ";
		return $sql;
	}

	/**
	 * Creates string for INSERT statement
	 * 
	 * @param array $what
	 */
	protected function prepareInsert(array $what){
		$a = '';
		$b = '';
		$i = 0;
		foreach($what as $k => $v){
			$a .= (($i>0)?', ':'').'`'.$k."`";
			$b .= (($i>0)?', ':'');
			if ($v === null){
				$b .= 'NULL';
				$update .= 'NULL';
			}else{
				$b .= "'".call_user_func($this->callbackQuote, $v)."'";
			}
			$i++;
		}
		 
		return array('a' => $a, 'b' => $b);
	}
	
	
	/**
	 * Open DBase file
	 */
	public function openDB(){
		$this->db = dbase_open($this->databaseFile, 0);  // Open database with read-only permissions
		if($this->db === false){
			throw new Exception("Can't open database file: ".$this->databaseFile, 1);
		}
	}
	
	
	/**
	* Close DBase file
	*/
	public function closeDB(){
		$this->db = dbase_close($this->db);
	}
	
	
}