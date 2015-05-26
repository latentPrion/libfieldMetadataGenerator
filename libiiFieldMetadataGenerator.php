<?php

namespace ii\FieldMetadataGenerator
{
	/**	EXPLANATION:
	 * Our intent here is to scan tables and output a descriptor file
	 * with information on how to validate the fields in that table.
	 *
	 * Ideally, we scan a database, and output descriptor information. There
	 * are three levels of information generated:
	 *	* Field validation.
	 *	* Logical Schema validation.
	 *	* Per-operation validation.
	 *
	 * This library will generate the first.
	 *
	 * The developer will produce the second, and the third will be found in
	 * business logic functions which process input before the DB is
	 * queried.
	 **/

class FieldMetadata
{
	public function __construct($name=NULL, $descRow=NULL)
	{
		$this->name = $name;
		$this->descRow = $descRow;

		$this->filteredIn = 0;
		$this->filteredOut = 0;
		$this->type = 0;
		$this->minLength = 0;
		$this->maxLength = 0;
		$this->minValue = 0;
		$this->maxValue = 0;

		$this->setType();
	}

	const
		TYPE_INTEGER		= 0,
		TYPE_STRING		= 1,
		TYPE_ENUM		= 2,
		TYPE_CHAR		= 3,
		TYPE_BOOLEAN		= 4,
		TYPE_NULL		= 5,
		TYPE_BLOB		= 6,
		TYPE_TIMESTAMP		= 7,
		TYPE_DATE		= 8,
		TYPE_DATETIME		= 9,
		TYPE_DECIMAL		= 10,
		TYPE_INTEGER_UNSIGNED	= 11,
		TYPE_LIST_TERMINATOR	= 256;

	static function lookupValidatorForField($fmdata, $vin)
	{
		/**	EXPLANATION:
		 * Takes an input type number and returns a string which will
		 * compile down to the correct validation parameters for the
		 * DB field.
		 *
		 *	TODO:
		 * I would like to be able to do range checking for integer and
		 * decimal fields. As in, run the value submitted by the browser
		 * through the validator and make sure it doesn't fall outside
		 * a range of valid values.
		 **/
		$validatorStrings = array(
			FieldMetadata::TYPE_INTEGER =>
				"$vin::allOf($vin::int()",
			FieldMetadata::TYPE_INTEGER_UNSIGNED =>
				"$vin::allOf($vin::int(), $vin::positive()",
			FieldMetadata::TYPE_DECIMAL =>
				"$vin::allOf($vin::numeric()",
			FieldMetadata::TYPE_STRING =>
				"$vin::allOf($vin::string()"
				.(($fmdata->maxLength == NULL || $fmdata->maxLength == "")
					? ""
					: ", $vin::length(null, " .$fmdata->maxLength .")")
				."",
			FieldMetadata::TYPE_ENUM =>
				"$vin::allOf($vin::int()",
			FieldMetadata::TYPE_CHAR =>
				"$vin::allOf($vin::string(), $vin::length(null, 1)",
			FieldMetadata::TYPE_BOOLEAN =>
				"$vin::oneOf($vin::int(), $vin::bool()",
			FieldMetadata::TYPE_NULL =>
				"$vin::allOf($vin::nullValue()",
			FieldMetadata::TYPE_BLOB =>
				"$vin::oneOf($vin::string(), $vin::nullValue()",
			FieldMetadata::TYPE_TIMESTAMP =>
				"$vin::allOf($vin::string(), $vin::date()",
			FieldMetadata::TYPE_DATE =>
				"$vin::allOf($vin::string(), $vin::date()",
			FieldMetadata::TYPE_DATETIME =>
				"$vin::allOf($vin::string(), $vin::date()",
		);

		$ret = $validatorStrings[$fmdata->type];

		if (!$fmdata->nullAllowed) {
			$ret .= ", $vin::notEmpty()";
		};

		return $ret . ")";
	}

	static function stringifyType($t)
	{
		$typeStrings = array(
			FieldMetadata::TYPE_INTEGER => "TYPE_INTEGER",
			FieldMetadata::TYPE_INTEGER_UNSIGNED => "TYPE_INTEGER_UNSIGNED",
			FieldMetadata::TYPE_DECIMAL => "TYPE_DECIMAL",
			FieldMetadata::TYPE_STRING => "TYPE_STRING",
			FieldMetadata::TYPE_ENUM => "TYPE_ENUM",
			FieldMetadata::TYPE_CHAR => "TYPE_CHAR",
			FieldMetadata::TYPE_BOOLEAN => "TYPE_BOOLEAN",
			FieldMetadata::TYPE_NULL => "TYPE_NULL",
			FieldMetadata::TYPE_BLOB => "TYPE_BLOB",
			FieldMetadata::TYPE_TIMESTAMP => "TYPE_TIMESTAMP",
			FieldMetadata::TYPE_DATE => "TYPE_DATE",
			FieldMetadata::TYPE_DATETIME => "TYPE_DATETIME"
		);

		if ($t >= count($typeStrings)) { throw new Exc("Stringify: Unknown type $t"); };
		return $typeStrings[$t];
	}

	private function setType()
	{
		if ($this->descRow == NULL) { throw new Exc("Please construct class first"); };

		$strippedType = preg_replace(
			"/\([0-9,[[:space:]]*\)/", "",
			strtolower($this->descRow["Type"]));

		$strippedLength = str_replace(
			$strippedType, "", strtolower($this->descRow["Type"]));

		$strippedLength = preg_replace("/[^0-9,]/", "", $strippedLength);

		$this->maxLength = $strippedLength;
		$this->nullAllowed = (strtolower($this->descRow["Null"]) == "no")
			? 0 : 1;

		switch ($strippedType)
		{
		case "bigint": $this->type = FieldMetadata::TYPE_INTEGER;
			break;
		case "int": $this->type = FieldMetadata::TYPE_INTEGER;
			break;
		case "mediumint": $this->type = FieldMetadata::TYPE_INTEGER;
			break;
		case "tinyint": $this->type = FieldMetadata::TYPE_INTEGER;
			break;
		case "bigint unsigned": $this->type = FieldMetadata::TYPE_INTEGER_UNSIGNED;
			break;
		case "int unsigned": $this->type = FieldMetadata::TYPE_INTEGER_UNSIGNED;
			break;
		case "mediumint unsigned": $this->type = FieldMetadata::TYPE_INTEGER_UNSIGNED;
			break;
		case "tinyint unsigned": $this->type = FieldMetadata::TYPE_INTEGER_UNSIGNED;
			break;
		case "decimal": $this->type = FieldMetadata::TYPE_DECIMAL;
			break;
		case "date": $this->type = FieldMetadata::TYPE_DATE;
			break;
		case "datetime": $this->type = FieldMetadata::TYPE_DATETIME;
			break;
		case "timestamp": $this->type = FieldMetadata::TYPE_TIMESTAMP;
			break;
		case "varbinary": $this->type = FieldMetadata::TYPE_BLOB;
			break;
		case "text": $this->type = FieldMetadata::TYPE_STRING;
			break;
		case "mediumtext": $this->type = FieldMetadata::TYPE_STRING;
			break;
		case "tinytext": $this->type = FieldMetadata::TYPE_STRING;
			break;
		case "varchar": $this->type = FieldMetadata::TYPE_STRING;
			break;

		default: throw new Exc(
			sprintf("Unknown type %s for field %s",
				$this->descRow["Type"], $this->descRow["Field"]));

			break;
		};
	}

	public		$name, $descRow,
			$type,
			$nullAllowed,
			$filteredIn, $filteredOut,
			$minLength, $maxLength,
			$minValue, $maxValue;
}

class Exc extends \Exception
{
}

class Database
{
	public function __construct($server, $username, $password, $dbname)
	{
		$this->connect($server, $username, $password, $dbname);
	}

	public function connect($server, $username, $password, $dbname)
	{
		$this->server = $server;
		$this->username = $username;
		$this->password = $password;
		$this->dbname = $dbname;

		$pdoDsn = sprintf("mysql:host=%s;dbname=%s;charset=utf8",
			$this->server, $this->dbname);

		$this->pdo = new \PDO($pdoDsn, $username, $password);
		$this->pdo->setAttribute(
			\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	}

	public function populateTables()
	{
		$stmt = $this->pdo->query("SHOW TABLES", \PDO::FETCH_NUM);
		$rs = $stmt->fetchAll();

		foreach ($rs as $row) {
			$this->tableNames[] = $row[0];
		};

		foreach ($this->tableNames as $tn){
			$this->tables[] = new Table($tn, $this->pdo);
		};

		foreach ($this->tables as $t)
		{
			$t->populateFields();

			foreach ($t->fields as $f) {
//				$f->populate();
			};
		};
	}

	public		$pdo, $pdoDsn,
			$server, $username, $password, $dbname,
			$tableNames, $tables;
}

class Table
{
	public function __construct($name, $pdo)
	{
		$this->pdo = $pdo;
		$this->name = $name;
	}

	public function populateFields()
	{
		$rs = $this->pdo->query("DESC " .$this->name);
		$rows = $rs->fetchAll();

		for ($i=0; $i<count($rows); $i++)
		{
			$this->fieldNames[] = $rows[$i]["Field"];
			$this->fields[] = new FieldMetadata($rows[$i]["Field"], $rows[$i]);
		};
	}

	public		$pdo,
			$name,			// string
			$fieldNames,		// string[]
			$fields;
}

} // End namespace.

?>
