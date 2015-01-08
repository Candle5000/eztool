<?php
require_once("/var/www/class/mysql.php");

if(!isset($argv[1])) {
	die("input file error\n");
}
$infile = fopen($argv[1], 'r');
if($infile == null) {
	die("input file error\n");
}
preg_match("/[0-9]+/", $argv[1], $match);
$zone = $match[0];

fread($infile, 257);
while(!feof($infile)) {
	$data = fread($infile, 1);
	$buf = unpack("Cdata", $data);
	if($buf["data"] == 255) {
		fseek($infile, -2, SEEK_CUR);
		break;
	}
}

for($i = 0; !feof($infile); $i++) {
	$data = fread($infile, 3);
	if($data == 'ARD') {
		break;
	}
	$buf = unpack("vid/Cname_length", $data);
	$npc[$i]["id"] = 65536 - $buf["id"];
	$npc[$i]["name"] = fread($infile, $buf["name_length"]);
	$data = fread($infile, 1);
	$buf = unpack("Cres", $data);
	$npc[$i]["res"] = "";
	for($j = $buf["res"]; $j > 0; $j--) {
		$data = fread($infile, 3);
		$npc[$i]["res"] .= $data;
	}
	$npc[$i]["dup"] = (($i > 0) && ($npc[$i]["name"] == $npc[$i-1]["name"]) && ($npc[$i]["res"] == $npc[$i-1]["res"]));
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
$m_data = new MySQL($user, $pw, $db);
$m_sql = "SELECT id,name FROM zone WHERE id=$zone";
$m_data->query($m_sql);
$zone_data = $m_data->fetch();
echo "ZONE ".$zone_data["id"]." : ".$zone_data["name"]."\n変更しますか？ (yで許可) ";
$stdin = trim(fgets(STDIN));
if($stdin == 'y') {
	echo "IDを入力してください ";
	while(true) {
		$stdin = trim(fgets(STDIN));
		if(!preg_match("/[^0-9]/", $stdin) && $stdin > 0 && $stdin < 256) {
			break;
		}
		echo "ERROR:無効な値です。 ";
	}
	$zone = $stdin;
}

foreach($npc as $n) {
	if(!$n["dup"]) {
		$m_sql = "SELECT id FROM monster WHERE zone=$zone AND id=".$n["id"];
		$m_data->query($m_sql);
		if($m_data->rows() == 0) {
			echo "ID:".$n["id"]." ".$n["name"]." を新規登録しますか？ (yで許可、eでイベントMOBとして登録) ";
			$stdin = trim(fgets(STDIN));
			if($stdin == 'y' || $stdin == 'e') {
				$event = ($stdin == 'e') ? 1 : 0;
				echo "分類を入力してください(0,100～152, 末尾にnを付加でNMとして登録) ";
				while(true) {
					$stdin = trim(fgets(STDIN));
					if(preg_match("/^([0-9]{3}|0{1})(n?)$/", $stdin, $match)) {
						$group = $match[1];
						$nm = ($match[2] == 'n') ? 1 : 0;
						if($stdin == 0 || ($stdin >= 100 && $stdin <= 152)) {
							break;
						}
					}
					echo "ERROR:無効な値です。 ";
				}
				$group = $stdin;
				$date = date("Y-m-d");
				$n["name"] = mysql_real_escape_string($n["name"]);
				$sql_data = "zone, id, name, nm, category, event, updated";
				$sql_value = "'$zone', '{$n["id"]}', '{$n["name"]}', '$nm', '$group', '$event', '$date'";
				$sql[] = "INSERT INTO monster (".$sql_data.") VALUES(".$sql_value.")";
			}
		}
	}
}

if(isset($sql)) {
	foreach($sql as $s) {
		$m_data->query($s);
	}
}
?>
