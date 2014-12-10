<?php
require_once("/var/www/class/mysql.php");

if(!isset($argv[1])) {
	die("input file error\n");
}
$infile = fopen($argv[1], 'r');
if($infile == null) {
	die("input file error\n");
}

fread($infile, 2);
$data = fread($infile, 1);
$buf = unpack("vcount", $data);
$count = $buf["count"];

for($i = 0; $i < $count | !feof($infile); $i++) {
	$data = fread($infile, 3);
	$buf = unpack("vid/Cname_length", $data);
	$item[$i]["id"] = $buf["id"];
	if($item[$i]["id"] == 0) {
		break;
	}
	$data = fread($infile, $buf["name_length"]);
	$item[$i]["name"] = mb_convert_encoding($data, "UTF-8", "SJIS-win");
	$data = fread($infile, 1);
	$buf = unpack("Ctext_length", $data);
	$data = fread($infile, $buf["text_length"]);
	$item[$i]["text"] = mb_convert_encoding($data, "UTF-8", "SJIS-win");
	$data = fread($infile, 6);
	$buf = unpack("Ctag1/Vtag2/Ctag3", $data);
	$item[$i]["rare"] = ($buf["tag1"] / 1) % 2;
	$item[$i]["notrade"] = ($buf["tag1"] / 2) % 2;
	$item[$i]["price"] = (($buf["tag1"] / 4) % 2) - 1;
	$item[$i]["tag2"] = $buf["tag2"];
	$item[$i]["stack"] = $buf["tag3"];
}
fclose($infile);

echo "ユーザ名: ";
$user = trim(fgets(STDIN));
echo "パスワード: ";
echo "\033[8m";
$pw = trim(fgets(STDIN));
echo "\033[0m";
echo "データベース: ";
$db = trim(fgets(STDIN));
$s_data = new MySQL($user, $pw, $db);

foreach($item as $it) {
	$s_sql = "SELECT * FROM items WHERE id=".$it["id"];
	$s_data->query($s_sql);
	if($s_data->rows() == 0) {
		echo "ID:".$it["id"]." ".$it["name"]." を新規登録しますか？ ";
		$stdin = trim(fgets(STDIN));
		if($stdin == 'y') {
			$date = date("Y-m-d");
			$sql_data = "id, name, text, rare, notrade, price, stack, updated";
			$sql_value = "'".$it["id"]."', '".$it["name"]."', '".$it["text"]."', '".$it["rare"]."', '".$it["notrade"]."', '".$it["price"]."', '"$it["stack"]."', '".$date."'";
			$sql[] = "INSERT INTO skill (".$sql_data.") VALUES(".$sql_value.")";
		}
	}
}

if(isset($sql)) {
	foreach($sql as $s) {
		$s_data->query($s);
	}
} else {
	echo "更新はありませんでした。";
}
?>
