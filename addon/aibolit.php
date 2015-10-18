#!/usr/bin/php
<?php
error_reporting(0);
ini_set('display_errors', 0);
define("PLUGIN_PATH", "/usr/local/ispmgr/var/.plugin_aibolit/");
define("AIBOLIT_EXCLUDE", "jpg,png,gif,jpeg,bmp,xml,pdf,doc,docx,xls,xlsx,ppt,pptx,css,psd,tar,gz,zip,rar,mp3");
$xml_string = file_get_contents("php://stdin");
$doc = simplexml_load_string($xml_string);
$func = $doc->params->func;
$sok = $doc->params->sok;
$elid = $doc->params->elid;
$user = $doc["user"];
$level = $doc["level"];

function check_owner($user, $elid) {
    if ($user != "root") {
        exec('/usr/local/ispmgr/sbin/mgrctl wwwdomain.edit elid=' . $elid . '|grep owner=', $exec_data);
        $owner = str_replace("owner=", "", $exec_data[0]);
        if ($owner != $user)
            return false;
        else
            return true;
    } else
        return true;
}

switch ($func) {
    case "aibolit.list";
        break;
    case "aibolit.delete";
        $list_elid = explode(",", $elid);
        foreach ($list_elid AS $row) {
            if (!check_owner($user, trim($row)))
                break;
            unlink(PLUGIN_PATH . trim($row) . ".html");
        }
        $doc->addChild("redirect", "alert('Статистика удалена, вы можете запустить новую проверку'); setTimeout(\"document.location='?func=wwwdomain'\", 100);");
        break;
    case "aibolit.result";
        if ($user == "root")
            $query = "";
        else
            $query = "|grep owner=" . $user . " ";
        exec('/usr/local/ispmgr/sbin/mgrctl wwwdomain' . $query, $exec_data);
        if ($exec_data) {
            $string = implode("\n", $exec_data);
            preg_match_all("|name=(.*)ip=|", $string, $matches);
            foreach ($matches[1] AS $row) {
                $domain = trim($row);
                if (is_file(PLUGIN_PATH . $domain . ".html")) {
                    $param = $doc->addChild('elem');
                    $val = $param->addChild('site', $domain);
                    $key = $param->addChild('data', date("Y.m.d H:i:s", filectime(PLUGIN_PATH . $domain . ".html")));
                }
            }
        }
        break;
    case "aibolit.stat";
        //закрываем окно по кнопке
        if ($sok == "ok") {
            $doc->addChild("ok", "ok");
            break;
        }
        if (!check_owner($user, $elid))
            break;
        $z = file_get_contents(PLUGIN_PATH . $elid . ".html");
        $doc->addChild("aibolit_stat", htmlspecialchars($z));
        break;
    case "aibolit.run";
        $result = is_file(PLUGIN_PATH . $elid . ".html");
        if ($result) {
            $doc->addChild("redirect", "alert('Антивирусная проверка завершена, вы можете просмотреть результат в статистике Антивируса, для перегенерации отчета удалите его в статистике'); setTimeout(\"document.location='?func=wwwdomain'\", 100);");
            break;
        }
        $chk_file = is_file(PLUGIN_PATH . $elid . '.lock');
        if ($chk_file) {
            $doc->addChild("redirect", "alert('Процесс антивирусной проверки запущен, после окончания отчет будет доступен на странице статистики Антивируса'); setTimeout(\"document.location='?func=wwwdomain'\", 100);");
        } else {
            exec('/usr/local/ispmgr/sbin/mgrctl wwwdomain.edit elid=' . $elid, $exec_data);
            foreach ($exec_data AS $row) {
                $parse = parse_ini_string($row);
                if (!empty($parse['docroot'])) {
                    $user_path = $parse['docroot'];
                    continue;
                }
            }
            $task = "php " . PLUGIN_PATH . "ai-bolit.php --path=\"" . $user_path . "\"  --report=\"" . PLUGIN_PATH . $elid . ".html\" --skip=\"" . AIBOLIT_EXCLUDE . "\"\n";
            $task.="/usr/local/ispmgr/sbin/mgrctl banner.new elid=aialert status=2 infotype=func info=aibolit.result  su=" . $user;
            file_put_contents(PLUGIN_PATH . $elid . '.lock', $task);
            exec("/usr/local/ispmgr/sbin/mgrctl banner.new elid=aiprogress status=3 param=" . $elid . " su=" . $user);
            $doc->addChild("redirect", "alert('Антивирусная проверка запущена, результаты будут доступны после окончательной проверки'); setTimeout(\"document.location='?func=wwwdomain'\", 100);");
        }
        break;
    default;
        break;
}
echo $doc->asXML();
