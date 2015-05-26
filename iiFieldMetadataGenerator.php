<?php

require("libiiFieldMetadataGenerator.php");

// using iiMySqlFieldMetadataGenerator;

function main()
{
	if (!isset($_GET["dbName"])) { throw new Exception("No DB name supplied"); };
	$dbName = $_GET["dbName"];

	$hostname = "localhost";
	$username = "root";
	$password = "tenkeitenkei_1";
	$vin = "rvld";
	// Subdir where the Respect/Validation library is contained.
	$validationLibSubdir = "respect-validation";

	if (isset($_GET["hostname"])) { $hostname = $_GET["hostname"]; };
	if (isset($_GET["username"])) { $hostname = $_GET["username"]; };
	if (isset($_GET["password"])) { $hostname = $_GET["password"]; };
	if (isset($_GET["validatorInstanceName"])) { $vin = $_GET["validatorInstanceName"]; };

	$fmg = new ii\FieldMetadataGenerator\database(
		$hostname, $username, $password, $dbName);

	$fmg->populateTables();

	if (!isset($_GET["ofName"])) { throw new Exception("No GET ofname var"); };
	$ofName = $_GET["ofName"];

	if (file_exists($ofName) && !isset($_GET["overwrite"])) {
		throw new Exception("Output file name already exists, and no overwrite GET var");
	};

	$ofHandle = fopen($ofName, "w");
	if (!$ofHandle) { throw new Exception("Failed to open ofName $ofName"); };

	fwrite($ofHandle, "<?php\n\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Validatable.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/AbstractRule.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/AbstractComposite.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/AllOf.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/OneOf.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Exceptions/ExceptionInterface.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Exceptions/ComponentException.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Factory.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/Int.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/NotEmpty.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/String.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/Length.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/Bool.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/Positive.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/Numeric.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/NullValue.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Rules/Date.php\";\n");
	fwrite($ofHandle, "\trequire \"$validationLibSubdir/library/Validator.php\";\n");
	fwrite($ofHandle, "\tuse \Respect\Validation\Validator as " .$vin .";\n\n");

	foreach ($fmg->tables as $t)
	{
		printf("<li><h1>Table: %s:</h1>\n", $t->name);
		fwrite($ofHandle, "\$tables[\"" .$t->name ."\"] = array(\n");

		foreach ($t->fields as $f)
		{
			echo "<ul>";
			printf("<li>Field: %s: type %s, maxlen %s, null? %s;</li>",
				$f->name,
				\ii\FieldMetadataGenerator\FieldMetadata::stringifyType($f->type),
				$f->maxLength,
				(($f->nullAllowed) ? "yes" : "no"));
			echo "</ul>";
			fwrite($ofHandle, "\t\"" .$f->name ."\" => "
				.\ii\FieldMetadataGenerator\FieldMetadata::lookupValidatorForField($f, $vin) .",\n");
		};

		printf("</li>");
		fwrite($ofHandle, ");\n");
	};

	fwrite($ofHandle, "\n ?>");
}

main();

?>
