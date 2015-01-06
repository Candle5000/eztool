<?php
require_once("/var/www/class/mysql.php");
$file = "update.txt";

if(!isset($argv[1])) {
	die("input file error\n");
}
$infile = fopen($argv[1], 'r');
if($infile == null) {
	die("input file error\n");
}

$data = fread($infile, 2);
$buf = unpack("vcount", $data);
$count = $buf["count"];

for($i = 0; $i < $count && !feof($infile); $i++) {
	$data = fread($infile, 2);
	$buf = unpack("vid", $data);
	$item[$i]["id"] = $buf["id"];
	if($item[$i]["id"] == 0) break;
	$data = fread($infile, 1);
	$buf = unpack("Cname_length", $data);
	$data = fread($infile, $buf["name_length"]);
	$item[$i]["name"] = mb_convert_encoding($data, "UTF-8", "SJIS-win");
	$data = fread($infile, 1);
	$buf = unpack("Ctext_length", $data);
	$data = ($buf["text_length"] > 0) ? fread($infile, $buf["text_length"]) : "";
	$item[$i]["text"] = mb_convert_encoding($data, "UTF-8", "SJIS-win");
	$data = fread($infile, 6);
	$buf = unpack("Ctag1/Vtag2/Ctag3", $data);
	$item[$i]["rare"] = ($buf["tag1"] / 1) % 2;
	$item[$i]["notrade"] = ($buf["tag1"] / 2) % 2;
	$item[$i]["price"] = (($buf["tag1"] / 4) % 2) - 1;
	$item[$i]["note"] = (($buf["tag1"] / 8) % 2) ? "破棄不可" : "特になし";
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

		//新規登録
		echo "ID:".$it["id"]." ".$it["name"]." を新規登録しますか？(yで許可) ";
		$stdin = trim(fgets(STDIN));
		if($stdin == 'y') {
			$date = date("Y-m-d");
			$sql_data = "id, name, text, rare, notrade, price, stack, note, updated";
			$sql_value = "'".$it["id"]."', '".$it["name"]."', '".$it["text"]."', '".$it["rare"]."', '".$it["notrade"]."', '".$it["price"]."', '".$it["stack"]."', '".$it["note"]."', '".$date."'";
			$sql[] = "INSERT INTO items (".$sql_data.") VALUES(".$sql_value.")";
			$text_add[] = "ID : ".$it["id"]." <a href=\"http://5000.pgw.jp/db/item/data/?id=".$it["id"]."\">".$it["name"]."</a>";
		}
	} else {

		//登録変更
		$array = $s_data->fetch();
		unset($sql_set);
		unset($diff);
		$data = array("name","text","rare","notrade","stack");
		if(($array["price"] != $it["price"]) && (($array["price"] == 0) != ($it["price"] == 0))) {
			$sql_set[] = "price='".$it["price"]."'";
			$diff[] = "price : ".$array["price"]." → ".$it["price"];
		}
		foreach($data as $d) {
			if(($it[$d] != "") && ($array[$d] != $it[$d])) {
				$sql_set[] = $d."='".$it[$d]."'";
				if($d == "text") {
					$diff[] = $d." :\n".$array[$d]."\n ↓\n".$it[$d];
				} else {
					$diff[] = $d." : ".$array[$d]." → ".$it[$d];
				}
			}
		}
		if(isset($sql_set)) {
			echo "ID:".$it["id"]." ".$it["name"]."\n".implode("\n", $diff)."\n変更しますか？(yで許可) ";
			$stdin = trim(fgets(STDIN));
			if($stdin == 'y') {
				$sql_set[] = "updated='".date("Y-m-d")."'";
				$sql[] = "UPDATE items SET ".implode(",", $sql_set)." WHERE id=".$it["id"];
				$text_upd[] = "ID : ".$it["id"]." <a href=\"http://5000.pgw.jp/db/item/data/?id=".$it["id"]."\">".$it["name"]."</a>\n".implode("\n", $diff);
			}
		}
	}
}

if(isset($sql)) {
	foreach($sql as $s) {
		$s_data->query($s);
	}
	$outfile = fopen($file, 'w');
	flock($outfile, LOCK_EX);
	if(isset($text_add)) {
		fputs($outfile, "今回の更新で追加された以下のアイテムをデータベースに追加しました。\n\n".implode("\n", $text_add)."\n\n");
	} else {
		fputs($outfile, "今回の更新で新たに追加されたアイテムはありません。\n\n");
	}
	if(isset($text_upd)) {
		fputs($outfile, "以下のアイテムの変更をデータベースに反映しました。\n\n".implode("\n\n", $text_upd)."\n");
	} else {
		fputs($outfile, "既存アイテムの変更はありません。\n");
	}
	flock($outfile, LOCK_UN);
	fclose($outfile);
	echo "データ更新完了。\n";
} else {
	echo "データ更新なし。\n";
}
?>
