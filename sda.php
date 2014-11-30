<?php
require_once("/var/www/class/mysql.php");

if(!isset($argv[1])) {
	die("input file error\n");
}
$infile = fopen($argv[1], 'r');
if($infile == null) {
	die("input file error\n");
}

fread($infile, 256);
while(!feof($infile)) {
	$data = fread($infile, 1);
	$buf = unpack("Cdata", $data);
	if($buf["data"] == '0') {
		break;
	}
}

for($i = 0; !feof($infile); $i++) {
	$data = fread($infile, 3);
	if($data == 'ARD') {
		break;
	}
	$buf = unpack("vid/Ccategory", $data);
	$skill[$i]["id"] = $buf["id"];
	$skill[$i]["category"] = $buf["category"];
	if($skill[$i]["category"] == 0 || $skill[$i]["category"] == 1 || $skill[$i]["category"] == 2) {
		if($skill[$i]["category"] != 2) {
			$data = fread($infile, 1);
			$buf = unpack("Cline", $data);
			$skill[$i]["line"] = $buf["line"];
		}
		if($skill[$i]["category"] == 1) {
			$data = fread($infile, 1);
		} else {
			$data = fread($infile, 4);
			$buf = unpack("vcost/vrecast", $data);
			$skill[$i]["cost"] = $buf["cost"];
			$skill[$i]["recast"] = $buf["recast"];
		}
	}
	if($skill[$i]["category"] == 0 || $skill[$i]["category"] == 2 || $skill[$i]["category"] == 4) {
		$data = fread($infile, 2);
		$buf = unpack("Huse/Htarget", $data);
		$skill[$i]["use"] = $buf["use"];
		$skill[$i]["target"] = $buf["target"];
	}
	$data = fread($infile, 1);
	$buf = unpack("Cname_length", $data);
	$data = fread($infile, $buf["name_length"]);
	$skill[$i]["name"] = mb_convert_encoding($data, "UTF-8", "SJIS-win");
	if($skill[$i]["category"] == 0 || $skill[$i]["category"] == 1 || $skill[$i]["category"] == 2) {
		$data = fread($infile, 1);
		$buf = unpack("Ctext_length", $data);
		$data = fread($infile, $buf["text_length"]);
		$skill[$i]["text"] = mb_convert_encoding($data, "UTF-8", "SJIS-win");
		if($skill[$i]["category"] != 2) {
			fread($infile, 1);
		}
	}
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

foreach($skill as $sk) {
	$s_sql = "SELECT id FROM skill WHERE id=".$sk["id"];
	$s_data->query($s_sql);
	if($s_data->rows() == 0) {
		echo "ID:".$sk["id"]." ".$sk["name"]." を新規登録しますか？ ";
		$stdin = trim(fgets(STDIN));
		if($stdin == 'y') {
			$sql_data = "id, name, category";
			if($sk["category"] == 0 || $sk["category"] == 1) {
				$group = $sk["line"] + 10;
			} else if($sk["category"] == 2) {
				if(floor($sk["id"] / 100) == 10) {
					$group = 40;
				} else {
					$group = floor($sk["id"] / 100) + 26;
				}
			} else if($sk["category"] == 4) {
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
			$sql_value = "'".$sk["id"]."', '".$sk["name"]."', '".$group."'";
			if($sk["category"] == 0 || $sk["category"] == 1 || $sk["category"] == 2) {
				if($sk["category"] != 1) {
					$sql_data .= ", cost, recast";
					$sql_value .= ", '".$sk["cost"]."', '".$sk["recast"]."'";
				}
				$sql_data .= ", text";
				$sql_value .= ", '".$sk["text"]."'";
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
