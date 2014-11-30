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
	$item[$i]["tag1"] = $buf["tag1"];
	$item[$i]["tag2"] = $buf["tag2"];
	$item[$i]["tag3"] = $buf["tag3"];
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
			$sql_data = "id, name, text, rare, notrade, price, stack";
			if($it["category"] == 0 || $it["category"] == 1) {
				$group = $it["line"] + 10;
			} else if($it["category"] == 2) {
				if(floor($it["id"] / 100) == 10) {
					$group = 40;
				} else {
					$group = floor($it["id"] / 100) + 26;
				}
			} else if($it["category"] == 4) {
				echo "分類を入力してください(61,62,63,66,67,69) ";
				while(true) {
					$stdin = trim(fgets(STDIN));
					if($stdin == "61" || $stdin == "62" || $stdin == "63" || $stdin == "66" || $stdin == "67" || $stdin == "69") {
						break;
					}
					echo "ERROR:無効な値です。 ";
				}
				$group = $stdin;
			}
			$sql_value = "'".$it["id"]."', '".$it["name"]."', '".$group."'";
			if($it["category"] == 0 || $it["category"] == 1 || $it["category"] == 2) {
				if($it["category"] != 1) {
					$sql_data .= ", cost, recast";
					$sql_value .= ", '".$it["cost"]."', '".$it["recast"]."'";
				}
				$sql_data .= ", text";
				$sql_value .= ", '".$it["text"]."'";
			}
			$date = date("Y-m-d");
			$sql_data .= ", updated";
			$sql_value .= ", '".$date."'";
			$sql[] = "INSERT INTO skill (".$sql_data.") VALUES(".$sql_value.")";
		}
	}
}

if(isset($sql)) {
	foreach($sql as $s) {
		$s_data->query($s);
	}
}
?>
