<?php
echo "ユーザ名: ";
$user = trim(fgets(STDIN));
echo "パスワード: ";
echo "\033[8m";
$pw = trim(fgets(STDIN));
echo "\033[0m";
echo "データベース: ";
$db = trim(fgets(STDIN));
$mysql = new mysqli("localhost", $user, $pw, $db);
if($mysql->connect_error) die("データベースの接続に失敗しました\n");

$sql = "SELECT * FROM `parameter`";
$result = $mysql->query($sql);
while($array = $result->fetch_array()) {
	$p_id["{$array["name"]}"] = $array["id"];
}
if(!isset($p_id)) die("データ読み込みに失敗しました\n");

$sql = "SELECT * FROM `equip_class`";
$result = $mysql->query($sql);
while($array = $result->fetch_array()) {
	$p_id["{$array["name"]}"] = $array["id"];
}
if(!isset($p_id)) die("データ読み込みに失敗しました\n");

$sql = "SELECT `id`, REPLACE(CONCAT(`text`, ' ',`note`), '\\n', ' ') AS `text` FROM `items` WHERE `id` BETWEEN '20001' AND '50000'";
$result = $mysql->query($sql);

while($array = $result->fetch_array()) {
	if(preg_match("/(<.*?>|\[.*?\]).*?Lv(([0-9]+)(～|→([0-9]+)))/", $array["text"], $i_match)) {
		$i_id = $array["id"];
		$class_id = floor(($i_id - 1) / 1000);
		$is_metal = strstr($i_match[1], '<') ? "TRUE" : "FALSE";
		$level_min = $i_match[3];
		$level_max = (isset($i_match[5]) && $i_match[5] != "") ? $i_match[5] : -1;
		$equip_values[] = "('$i_id', '$class_id', '$level_min', '$level_max', $is_metal)";
		$array["text"] = str_replace("特になし", "", $array["text"]);
		$array["text"] = str_replace("DLY", "DELAY", $array["text"]);
		$array["text"] = str_replace(" -", "-", $array["text"]);
		$array["text"] = str_replace("：", ":", $array["text"]);
		$array["text"] = str_replace("％", "%", $array["text"]);
		$array["text"] = str_replace("*", "", $array["text"]);
		$array["text"] = str_replace("T:", "TRAP:", $array["text"]);
		$array["text"] = str_replace("低", "抵", $array["text"]);
		$array["text"] = str_replace("金属値-", "金属-", $array["text"]);
		$array["text"] = str_replace("DEX -", "DEX-", $array["text"]);
		$array["text"] = str_replace("13H回復量", "13 H回復量", $array["text"]);
		$array["text"] = str_replace("24 STR-9", "24", $array["text"]);
		$array["text"] = str_replace("属性ダメージ", "属性DMG", $array["text"]);
		$array["text"] = str_replace("アップ", "UP", $array["text"]);
		$array["text"] = str_replace("ダウン", "DOWN", $array["text"]);
		$array["text"] = str_replace("盾防御発動率UP", "盾防率UP", $array["text"]);
		$array["text"] = str_replace("PROC発動率UP", "PROC率UP", $array["text"]);
		$array["text"] = str_replace("アンデット", "アンデッド", $array["text"]);
		$array["text"] = str_replace("詠唱妨害率", "詠唱中断率", $array["text"]);
		$array["text"] = preg_replace("/([^A])P:/", "\$1PROC:", $array["text"]);
		$array["text"] = preg_replace("/(H|ヒール)回復(量)?/", "H回復量", $array["text"]);
		$array["text"] = preg_replace("/(火|水|土|風|光|闇)(火|水|土|風|光|闇)攻(\\+[0-9]+)/", "\$1攻\$3 \$2攻\$3", $array["text"]);
		$array["text"] = preg_replace("/(火|水|土|風|光|闇)(火|水|土|風|光|闇)命(\\+[0-9]+)/", "\$1命\$3 \$2命\$3", $array["text"]);
		$array["text"] = preg_replace("/(火|水|土|風|光|闇)(火|水|土|風|光|闇)抵(\\+[0-9]+)/", "\$1抵\$3 \$2抵\$3", $array["text"]);
		$array["text"] = preg_replace("/Crit(火|水|土|風|光|闇)(火|水|土|風|光|闇)(\\+[0-9]+%)/", "Crit\$1\$3 Crit\$2\$3", $array["text"]);
		$parameters = explode(" ", str_replace($i_match[0], "", $array["text"]));
		foreach($parameters as $parameter) {
			if($parameter != "") {
				if(preg_match("/^(.*?)(([\\+-]?[0-9]+)[%]?|[\\+]?([0-9]+)～([0-9]+))?$/", $parameter, $p_match)) {
					if(preg_match("/(毒|麻痺|沈黙|暗闇|失神|睡眠|鈍足|禁足|窒息|スロウ|HP[0-9]+%)→/", $p_match[1], $a_match)) {
						$name = str_replace($a_match[0], "", $p_match[1]);
						$adversity = "TRUE";
					} else {
						$name = $p_match[1];
						$adversity = "FALSE";
					}
					if(isset($p_match[2]) && $p_match[2] != "") {
						if(isset($p_match[3]) && $p_match[3] != "") {
							$value_max = $p_match[3] + 0;
							$value_min = 0;
						} else {
							$value_max = $p_match[5] + 0;
							$value_min = $p_match[4] + 0;
						}
					} else {
						$value_max = 1;
						$value_min = 0;
					}
					if($name == "DMG") {
						$dmg_max = $value_max;
						$dmg_min = $value_min;
					}
					if($name == "DELAY") {
						$delay = $value_max;
						$dpd_max = round(($dmg_max / $delay), 3);
						$dpd_min = round(($dmg_min / $delay), 3);
						$parameter_values[] = "('$i_id', '{$p_id["D/D"]}', FALSE, '$dpd_min', '$dpd_max')";
					}
					if(isset($p_id[$name])) {
						$parameter_values[] = "('$i_id', '{$p_id[$name]}', $adversity, '$value_min', '$value_max')";
					} else {
						echo "ERROR : id='$i_id' parameter_name='$name'\n";
					}
				} else {
					echo "ERROR : parameter='$parameter\n'";
				}
			}
		}
	}
}

if(isset($equip_values) && isset($parameter_values)) {
	$sql = "DELETE FROM `equip_parameter`";
	$mysql->query($sql);

	$sql = "DELETE FROM `equip`";
	$mysql->query($sql);

	$sql = "INSERT INTO `equip` VALUES ".implode($equip_values, ",");
	$mysql->query($sql);

	$sql = "INSERT INTO `equip_parameter` VALUES ".implode($parameter_values, ",");
	$mysql->query($sql);
} else {
	die("データの読み込みに失敗しました\n");
}
?>
