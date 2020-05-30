<?php
require_once("include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
require_once(get_langfile_path("", true));
loggedinorreturn();
parked();
if ($enableoffer == 'no') {
    permissiondenied();
}
function bark($msg)
{
    global $lang_offers;
    stdhead($lang_offers['head_offer_error']);
    stdmsg($lang_offers['std_error'], $msg);
    stdfoot();
    exit;
}

if ($_GET["category"]) {
    $categ = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    if (!is_valid_id($categ)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}

if ($_GET["id"]) {
    $id = 0 + htmlspecialchars($_GET["id"]);
    if (preg_match('/^[0-9]+$/', !$id)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}

//==== add offer
if ($_GET["add_offer"]) {
    if (get_user_class() < $addoffer_class) {
        permissiondenied();
    }
    $add_offer = 0 + $_GET["add_offer"];
    if ($add_offer != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    stdhead($lang_offers['head_offer']);

    print("<p>".$lang_offers['text_red_star_required']."</p>");

    print("<div align=\"center\"><form id=\"compose\" action=\"?new_offer=1\" name=\"compose\" method=\"post\">".
    "<table width=940 border=0 cellspacing=0 cellpadding=5><tr><td class=colhead align=center colspan=2>".$lang_offers['text_offers_open_to_all']."</td></tr>\n");

    $s = "<select name=type>\n<option value=0>".$lang_offers['select_type_select']."</option>\n";
    $cats = genrelist($browsecatmode);
    foreach ($cats as $row) {
        $s .= "<option value=".$row["id"].">" . htmlspecialchars($row["name"]) . "</option>\n";
    }
    $s .= "</select>\n";
    print("<tr><td class=rowhead align=right><b>".$lang_offers['row_type']."<font color=red>*</font></b></td><td class=rowfollow align=left> $s</td></tr>".
    "<tr><td class=rowhead align=right><b>".$lang_offers['row_title']."<font color=red>*</font></b></td><td class=rowfollow align=left><input type=text name=name style=\"width: 650px;\" />".
    "</td></tr><tr><td class=rowhead align=right><b>".$lang_offers['row_post_or_photo']."</b></td><td class=rowfollow align=left>".
    "<input type=text name=picture style=\"width: 650px;\"><br />".$lang_offers['text_link_to_picture']."</td></tr>".
    "<tr><td class=rowhead align=right valign=top><b>".$lang_offers['row_description']."<b><font color=red>*</font></td><td class=rowfollow align=left>\n");
    textbbcode("compose", "body", $body, false);
    print("</td></tr><tr><td class=toolbox align=center colspan=2><input id=qr type=submit class=btn value=".$lang_offers['submit_add_offer']." ></td></tr></table></form><br />\n");
    stdfoot();
    die;
}
//=== end add offer

//=== take new offer
if ($_GET["new_offer"]) {
    if (get_user_class() < $addoffer_class) {
        permissiondenied();
    }
    $new_offer = 0 + $_GET["new_offer"];
    if ($new_offer != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $userid = 0 + $CURUSER["id"];
    if (preg_match("/^[0-9]+$/", !$userid)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $name = $_POST["name"];
    if ($name == "") {
        bark($lang_offers['std_must_enter_name']);
    }

    $cat = (0 + $_POST["type"]);
    if (!is_valid_id($cat)) {
        bark($lang_offers['std_must_select_category']);
    }

    $descrmain = $_POST["body"];
    if (!$descrmain) {
        bark($lang_offers['std_must_enter_description']);
    }

    if (!empty($_POST['picture'])) {
        $picture = $_POST["picture"];
        if (!preg_match("/^http:\/\/[^\s'\"<>]+\.(jpg|gif|png)$/i", $picture)) {
            stderr($lang_offers['std_error'], $lang_offers['std_wrong_image_format']);
        }
        $pic = "[img]".$picture."[/img]\n";
    }

    $descr = $pic;
    $descr .= $descrmain;

    $res = \NexusPHP\Components\Database::query("SELECT name FROM offers WHERE name =".\NexusPHP\Components\Database::escape($_POST[name])) or sqlerr(__FILE__, __LINE__);
    $arr = mysqli_fetch_assoc($res);
    if (!$arr['name']) {
        //===add karma //=== uncomment if you use the mod
        //\NexusPHP\Components\Database::query("UPDATE users SET seedbonus = seedbonus+10.0 WHERE id = $CURUSER[id]") or sqlerr(__FILE__, __LINE__);
        //===end

        $ret = \NexusPHP\Components\Database::query("INSERT INTO offers (userid, name, descr, category, added) VALUES (" .
        implode(",", \NexusPHP\Components\Database::escape(array($CURUSER["id"], $name, $descr, 0 + $_POST["type"]))) .
        ", '" . date("Y-m-d H:i:s") . "')");
        if (!$ret) {
            if (\NexusPHP\Components\Database::errno() == 1062) {
                bark("!!!");
            }
            bark("mysql puked: ".\NexusPHP\Components\Database::error());
        }
        $id = \NexusPHP\Components\Database::insert_id();

        write_log("offer $name was added by ".$CURUSER[username], 'normal');

        header("Refresh: 0; url=offers.php?id=$id&off_details=1");

        stdhead($lang_offers['head_success']);
    } else {
        stderr($lang_offers['std_error'], $lang_offers['std_offer_exists']."<a class=altlink href=offers.php>".$lang_offers['text_view_all_offers']."</a>", false);
    }
    stdfoot();
    die;
}
//==end take new offer

//=== offer details
if ($_GET["off_details"]) {
    $off_details = 0 + $_GET["off_details"];
    if ($off_details != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $id = 0+$_GET["id"];
    if (!$id) {
        die();
    }
    //stderr("Error", "I smell a rat!");
    
    $res = \NexusPHP\Components\Database::query("SELECT * FROM offers WHERE id = $id") or sqlerr(__FILE__, __LINE__);
    $num = mysqli_fetch_array($res);

    $s = $num["name"];

    stdhead($lang_offers['head_offer_detail_for']." \"".$s."\"");
    print("<h1 align=\"center\" id=\"top\">".htmlspecialchars($s)."</h1>");

    print("<table width=\"940\" cellspacing=\"0\" cellpadding=\"5\">");
    $offertime = gettime($num['added'], true, false);
    if ($CURUSER['timetype'] != 'timealive') {
        $offertime = $lang_offers['text_at'].$offertime;
    } else {
        $offertime = $lang_offers['text_blank'].$offertime;
    }
    tr($lang_offers['row_info'], $lang_offers['text_offered_by'].get_username($num['userid']).$offertime, 1);
    if ($num["allowed"] == "pending") {
        $status="<font color=\"red\">".$lang_offers['text_pending']."</font>";
    } elseif ($num["allowed"] == "allowed") {
        $status="<font color=\"green\">".$lang_offers['text_allowed']."</font>";
    } else {
        $status="<font color=\"red\">".$lang_offers['text_denied']."</font>";
    }
    tr($lang_offers['row_status'], $status, 1);
    //=== if you want to have a pending thing for uploaders use this next bit
    if (get_user_class() >= $offermanage_class && $num["allowed"] == "pending") {
        tr($lang_offers['row_allow'], "<table><tr><td class=\"embedded\"><form method=\"post\" action=\"?allow_offer=1\"><input type=\"hidden\" value=\"".$id."\" name=\"offerid\" />".
    "<input class=\"btn\" type=\"submit\" value=\"".$lang_offers['submit_allow']."\" />&nbsp;&nbsp;</form></td><td class=\"embedded\"><form method=\"post\" action=\"?id=".$id."&amp;finish_offer=1\">".
    "<input type=\"hidden\" value=\"".$id."\" name=\"finish\" /><input class=\"btn\" type=\"submit\" value=\"".$lang_offers['submit_let_votes_decide']."\" /></form></td></tr></table>", 1);
    }

    $zres = \NexusPHP\Components\Database::query("SELECT COUNT(*) from offervotes where vote='yeah' and offerid=$id");
    $arr = mysqli_fetch_row($zres);
    $za = $arr[0];
    $pres = \NexusPHP\Components\Database::query("SELECT COUNT(*) from offervotes where vote='against' and offerid=$id");
    $arr2 = mysqli_fetch_row($pres);
    $protiv = $arr2[0];
    //=== in the following section, there is a line to report comment... either remove the link or change it to work with your report script :)

    //if pending
    if ($num["allowed"] == "pending") {
        tr($lang_offers['row_vote'], "<b>".
        "<a href=\"?id=".$id."&amp;vote=yeah\"><font color=\"green\">".$lang_offers['text_for']."</font></a></b>".(get_user_class() >= $againstoffer_class ? " - <b><a href=\"?id=".$id."&amp;vote=against\">".
        "<font color=\"red\">".$lang_offers['text_against']."</font></a></b>" : ""), 1);
        tr(
            $lang_offers['row_vote_results'],
            "<b>".$lang_offers['text_for'].":</b> $za  <b>".$lang_offers['text_against']."</b> $protiv &nbsp; &nbsp; <a href=\"?id=".$id."&amp;offer_vote=1\"><i>".$lang_offers['text_see_vote_detail']."</i></a>",
            1
        );
    }
    //===upload torrent message
    if ($num["allowed"] == "allowed" && $CURUSER["id"] != $num["userid"]) {
        tr($lang_offers['row_offer_allowed'], $lang_offers['text_voter_receives_pm_note'], 1);
    }
    if ($num["allowed"] == "allowed" && $CURUSER["id"] == $num["userid"]) {
        tr(
            $lang_offers['row_offer_allowed'],
            $lang_offers['text_urge_upload_offer_note'],
            1
        );
    }
    if ($CURUSER[id] == $num[userid] || get_user_class() >= $offermanage_class) {
        $edit = "<a href=\"?id=".$id."&amp;edit_offer=1\"><img class=\"dt_edit\" src=\"pic/trans.gif\" alt=\"edit\" />&nbsp;<b><font class=\"small\">".$lang_offers['text_edit_offer'] . "</font></b></a>&nbsp;|&nbsp;";
        $delete = "<a href=\"?id=".$id."&amp;del_offer=1&amp;sure=0\"><img class=\"dt_delete\" src=\"pic/trans.gif\" alt=\"delete\" />&nbsp;<b><font class=\"small\">".$lang_offers['text_delete_offer']."</font></b></a>&nbsp;|&nbsp;";
    }
    $report = "<a href=\"report.php?reportofferid=".$id."\"><img class=\"dt_report\" src=\"pic/trans.gif\" alt=\"report\" />&nbsp;<b><font class=\"small\">".$lang_offers['report_offer']."</font></b></a>";
    tr($lang_offers['row_action'], $edit . $delete .$report, 1);
    if ($num["descr"]) {
        $off_bb = format_comment($num["descr"]);
        tr($lang_offers['row_description'], $off_bb, 1);
    }
    print("</table>");
    // -----------------COMMENT SECTION ---------------------//
    $commentbar = "<p align=\"center\"><a class=\"index\" href=\"comment.php?action=add&amp;pid=".$id."&amp;type=offer\">".$lang_offers['text_add_comment']."</a></p>\n";
    $subres = \NexusPHP\Components\Database::query("SELECT COUNT(*) FROM comments WHERE offer = $id");
    $subrow = mysqli_fetch_array($subres);
    $count = $subrow[0];
    if (!$count) {
        print("<h1 id=\"startcomments\" align=\"center\">".$lang_offers['text_no_comments']."</h1>\n");
    } else {
        list($pagertop, $pagerbottom, $limit) = pager(10, $count, "offers.php?id=$id&off_details=1&", array(lastpagedefault => 1));

        $subres = \NexusPHP\Components\Database::query("SELECT id, text, user, added, editedby, editdate FROM comments  WHERE offer = " . \NexusPHP\Components\Database::escape($id) . " ORDER BY id $limit") or sqlerr(__FILE__, __LINE__);
        $allrows = array();
        while ($subrow = mysqli_fetch_array($subres)) {
            $allrows[] = $subrow;
        }

        //end_frame();
        //print($commentbar);
        print($pagertop);

        commenttable($allrows, "offer", $id);
        print($pagerbottom);
    }
    print("<table style='border:1px solid #000000;'><tr>".
"<td class=\"text\" align=\"center\"><b>".$lang_offers['text_quick_comment']."</b><br /><br />".
"<form id=\"compose\" name=\"comment\" method=\"post\" action=\"comment.php?action=add&amp;type=offer\" onsubmit=\"return postvalid(this);\">".
"<input type=\"hidden\" name=\"pid\" value=\"".$id."\" /><br />");
    quickreply('comment', 'body', $lang_offers['submit_add_comment']);
    print("</form></td></tr></table>");
    print($commentbar);
    stdfoot();
    die;
}
//=== end offer details
//=== allow offer by staff
if ($_GET["allow_offer"]) {
    if (get_user_class() < $offermanage_class) {
        stderr($lang_offers['std_access_denied'], $lang_offers['std_mans_job']);
    }

    $allow_offer = 0 + $_GET["allow_offer"];
    if ($allow_offer != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    //=== to allow the offer  credit to S4NE for this next bit :)
    //if ($_POST["offerid"]){
    $offid = 0 + $_POST["offerid"];
    if (!is_valid_id($offid)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $res = \NexusPHP\Components\Database::query("SELECT users.username, offers.userid, offers.name FROM offers inner join users on offers.userid = users.id where offers.id = $offid") or sqlerr(__FILE__, __LINE__);
    $arr = mysqli_fetch_assoc($res);
    if ($offeruptimeout_main) {
        $timeouthour = floor($offeruptimeout_main/3600);
        $timeoutnote = $lang_offers_target[get_user_lang($arr["userid"])]['msg_you_must_upload_in'].$timeouthour.$lang_offers_target[get_user_lang($arr["userid"])]['msg_hours_otherwise'];
    } else {
        $timeoutnote = "";
    }
    $msg = "$CURUSER[username]".$lang_offers_target[get_user_lang($arr["userid"])]['msg_has_allowed']."[b][url=". get_protocol_prefix() . $BASEURL ."/offers.php?id=$offid&off_details=1]" . $arr[name] . "[/url][/b]. ".$lang_offers_target[get_user_lang($arr["userid"])]['msg_find_offer_option'].$timeoutnote;

    $subject = $lang_offers_target[get_user_lang($arr["userid"])]['msg_your_offer_allowed'];
    $allowedtime = date("Y-m-d H:i:s");
    \NexusPHP\Components\Database::query("INSERT INTO messages (sender, receiver, added, msg, subject) VALUES(0, $arr[userid], '" . $allowedtime . "', " . \NexusPHP\Components\Database::escape($msg) . ", ".\NexusPHP\Components\Database::escape($subject).")") or sqlerr(__FILE__, __LINE__);
    \NexusPHP\Components\Database::query("UPDATE offers SET allowed = 'allowed', allowedtime = '".$allowedtime."' WHERE id = $offid") or sqlerr(__FILE__, __LINE__);

    write_log("$CURUSER[username] allowed offer $arr[name]", 'normal');
    header("Refresh: 0; url=" . get_protocol_prefix() . "$BASEURL/offers.php?id=$offid&off_details=1");
}
//=== end allow the offer

//=== allow offer by vote
if ($_GET["finish_offer"]) {
    if (get_user_class() < $offermanage_class) {
        stderr($lang_offers['std_access_denied'], $lang_offers['std_have_no_permission']);
    }

    $finish_offer = 0 + $_GET["finish_offer"];
    if ($finish_offer != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $offid = 0 + $_POST["finish"];
    if (!is_valid_id($offid)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $res = \NexusPHP\Components\Database::query("SELECT users.username, offers.userid, offers.name FROM offers inner join users on offers.userid = users.id where offers.id = $offid") or sqlerr(__FILE__, __LINE__);
    $arr = mysqli_fetch_assoc($res);

    $voteresyes = \NexusPHP\Components\Database::query("SELECT COUNT(*) from offervotes where vote='yeah' and offerid=$offid");
    $arryes = mysqli_fetch_row($voteresyes);
    $yes = $arryes[0];
    $voteresno = \NexusPHP\Components\Database::query("SELECT COUNT(*) from offervotes where vote='against' and offerid=$offid");
    $arrno = mysqli_fetch_row($voteresno);
    $no = $arrno[0];

    if ($yes == '0' && $no == '0') {
        stderr($lang_offers['std_sorry'], $lang_offers['std_no_votes_yet']."<a  href=offers.php?id=$offid&off_details=1>".$lang_offers['std_back_to_offer_detail']."</a>", false);
    }
    $finishvotetime = date("Y-m-d H:i:s");
    if (($yes - $no)>=$minoffervotes) {
        if ($offeruptimeout_main) {
            $timeouthour = floor($offeruptimeout_main/3600);
            $timeoutnote = $lang_offers_target[get_user_lang($arr["userid"])]['msg_you_must_upload_in'].$timeouthour.$lang_offers_target[get_user_lang($arr["userid"])]['msg_hours_otherwise'];
        } else {
            $timeoutnote = "";
        }
        $msg = $lang_offers_target[get_user_lang($arr["userid"])]['msg_offer_voted_on']."[b][url=" . get_protocol_prefix() . $BASEURL."/offers.php?id=$offid&off_details=1]" . $arr[name] . "[/url][/b].". $lang_offers_target[get_user_lang($arr["userid"])]['msg_find_offer_option'].$timeoutnote;
        \NexusPHP\Components\Database::query("UPDATE offers SET allowed = 'allowed',allowedtime ='".$finishvotetime."' WHERE id = $offid") or sqlerr(__FILE__, __LINE__);
    } elseif (($no - $yes)>=$minoffervotes) {
        $msg = $lang_offers_target[get_user_lang($arr["userid"])]['msg_offer_voted_off']."[b][url=". get_protocol_prefix() . $BASEURL."/offers.php?id=$offid&off_details=1]" . $arr[name] . "[/url][/b].".$lang_offers_target[get_user_lang($arr["userid"])]['msg_offer_deleted'] ;
        \NexusPHP\Components\Database::query("UPDATE offers SET allowed = 'denied' WHERE id = $offid") or sqlerr(__FILE__, __LINE__);
    }
    //===use this line if you DO HAVE subject in your PM system
    $subject = $lang_offers_target[get_user_lang($arr[userid])]['msg_your_offer'].$arr[name].$lang_offers_target[get_user_lang($arr[userid])]['msg_voted_on'];
    \NexusPHP\Components\Database::query("INSERT INTO messages (sender, subject, receiver, added, msg) VALUES(0, ".\NexusPHP\Components\Database::escape($subject).", $arr[userid], '" . $finishvotetime . "', " . \NexusPHP\Components\Database::escape($msg) . ")") or sqlerr(__FILE__, __LINE__);
    //===use this line if you DO NOT subject in your PM system
    //\NexusPHP\Components\Database::query("INSERT INTO messages (sender, receiver, added, msg) VALUES(0, $arr[userid], '" . date("Y-m-d H:i:s") . "', " . \NexusPHP\Components\Database::escape($msg) . ")") or sqlerr(__FILE__, __LINE__);
    write_log("$CURUSER[username] closed poll $arr[name]", 'normal');

    header("Refresh: 0; url=" . get_protocol_prefix() . "$BASEURL/offers.php?id=$offid&off_details=1");
    die;
}
//===end allow offer by vote

//=== edit offer

if ($_GET["edit_offer"]) {
    $edit_offer = 0 + $_GET["edit_offer"];
    if ($edit_offer != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $id = 0 + $_GET["id"];

    $res = \NexusPHP\Components\Database::query("SELECT * FROM offers WHERE id = $id") or sqlerr(__FILE__, __LINE__);
    $num = mysqli_fetch_array($res);

    $timezone = $num["added"];

    $s = $num["name"];
    $id2 = $num["category"];

    if ($CURUSER["id"] != $num["userid"] && get_user_class() < $offermanage_class) {
        stderr($lang_offers['std_error'], $lang_offers['std_cannot_edit_others_offer']);
    }

    $body = htmlspecialchars($num["descr"]);
    $s2 = "<select name=\"category\">\n";

    $cats = genrelist($browsecatmode);

    foreach ($cats as $row) {
        $s2 .= "<option value=\"" . $row["id"] . "\" ".($row['id'] == $id2 ? " selected=\"selected\"" : "").">" . htmlspecialchars($row["name"]) . "</option>\n";
    }
    $s2 .= "</select>\n";

    stdhead($lang_offers['head_edit_offer'].": $s");
    $title = htmlspecialchars(trim($s));
    
    print("<form id=\"compose\" method=\"post\" name=\"compose\" action=\"?id=".$id."&amp;take_off_edit=1\">".
    "<table width=\"940\" cellspacing=\"0\" cellpadding=\"3\"><tr><td class=\"colhead\" align=\"center\" colspan=\"2\">".$lang_offers['text_edit_offer']."</td></tr>");
    tr($lang_offers['row_type']."<font color=\"red\">*</font>", $s2, 1);
    tr($lang_offers['row_title']."<font color=\"red\">*</font>", "<input type=\"text\" style=\"width: 650px\" name=\"name\" value=\"".$title."\" />", 1);
    tr($lang_offers['row_post_or_photo'], "<input type=\"text\" name=\"picture\" style=\"width: 650px\" value='' /><br />".$lang_offers['text_link_to_picture'], 1);
    print("<tr><td class=\"rowhead\" align=\"right\" valign=\"top\"><b>".$lang_offers['row_description']."<font color=\"red\">*</font></b></td><td class=\"rowfollow\" align=\"left\">");
    textbbcode("compose", "body", $body, false);
    print("</td></tr>");
    print("<tr><td class=\"toolbox\" style=\"vertical-align: middle; padding-top: 10px; padding-bottom: 10px;\" align=\"center\" colspan=\"2\"><input id=\"qr\" type=\"submit\" value=\"".$lang_offers['submit_edit_offer']."\" class=\"btn\" /></td></tr></table></form><br />\n");
    stdfoot();
    die;
}
//=== end edit offer

//==== take offer edit
if ($_GET["take_off_edit"]) {
    $take_off_edit = 0 + $_GET["take_off_edit"];
    if ($take_off_edit != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $id = 0 + $_GET["id"];

    $res = \NexusPHP\Components\Database::query("SELECT userid FROM offers WHERE id = $id") or sqlerr(__FILE__, __LINE__);
    $num = mysqli_fetch_array($res);

    if ($CURUSER[id] != $num[userid] && get_user_class() < $offermanage_class) {
        stderr($lang_offers['std_error'], $lang_offers['std_access_denied']);
    }

    $name = $_POST["name"];

    if (!empty($_POST['picture'])) {
        $picture = $_POST["picture"];
        if (!preg_match("/^http:\/\/[^\s'\"<>]+\.(jpg|gif|png)$/i", $picture)) {
            stderr($lang_offers['std_error'], $lang_offers['std_wrong_image_format']);
        }
        $pic = "[img]".$picture."[/img]\n";
    }
    $descr = "$pic";
    $descr .= $_POST["body"];
    if (!$name) {
        bark($lang_offers['std_must_enter_name']);
    }
    if (!$descr) {
        bark($lang_offers['std_must_enter_description']);
    }
    $cat = (0 + $_POST["category"]);
    if (!is_valid_id($cat)) {
        bark($lang_offers['std_must_select_category']);
    }

    $name = \NexusPHP\Components\Database::escape($name);
    $descr = \NexusPHP\Components\Database::escape($descr);
    $cat = \NexusPHP\Components\Database::escape($cat);

    \NexusPHP\Components\Database::query("UPDATE offers SET category=$cat, name=$name, descr=$descr where id=".\NexusPHP\Components\Database::escape($id));

    //header("Refresh: 0; url=offers.php?id=$id&off_details=1");
}
//======end take offer edit

//=== offer votes list
if ($_GET["offer_vote"]) {
    $offer_vote = 0 + $_GET["offer_vote"];
    if ($offer_vote != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $offerid = 0 + htmlspecialchars($_GET[id]);

    $res2 = \NexusPHP\Components\Database::query("SELECT COUNT(*) FROM offervotes WHERE offerid = ".\NexusPHP\Components\Database::escape($offerid)) or sqlerr(__FILE__, __LINE__);
    $row = mysqli_fetch_array($res2);
    $count = $row[0];

    $offername = \NexusPHP\Components\Database::single("offers", "name", "WHERE id=".\NexusPHP\Components\Database::escape($offerid));
    stdhead($lang_offers['head_offer_voters']." - \"".$offername."\"");

    print("<h1 align=center>".$lang_offers['text_vote_results_for']." <a  href=offers.php?id=$offerid&off_details=1><b>".htmlspecialchars($offername)."</b></a></h1>");

    $perpage = 25;
    list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, $_SERVER["PHP_SELF"] ."?id=".$offerid."&offer_vote=1&");
    $res = \NexusPHP\Components\Database::query("SELECT * FROM offervotes WHERE offerid=".\NexusPHP\Components\Database::escape($offerid)." ".$limit) or sqlerr(__FILE__, __LINE__);

    if (mysqli_num_rows($res) == 0) {
        print("<p align=center><b>".$lang_offers['std_no_votes_yet']."</b></p>\n");
    } else {
        echo $pagertop;
        print("<table border=1 cellspacing=0 cellpadding=5><tr><td class=colhead>".$lang_offers['col_user']."</td><td class=colhead align=left>".$lang_offers['col_vote']."</td>\n");

        while ($arr = mysqli_fetch_assoc($res)) {
            if ($arr[vote] == 'yeah') {
                $vote = "<b><font color=green>".$lang_offers['text_for']."</font></b>";
            } elseif ($arr[vote] == 'against') {
                $vote = "<b><font color=red>".$lang_offers['text_against']."</font></b>";
            } else {
                $vote = "unknown";
            }

            print("<tr><td class=rowfollow>" . get_username($arr['userid']) . "</td><td class=rowfollow align=left >".$vote."</td></tr>\n");
        }
        print("</table>\n");
        echo $pagerbottom;
    }

    stdfoot();
    die;
}
//=== end offer votes list

//=== offer votes
if ($_GET["vote"]) {
    $offerid = 0 + htmlspecialchars($_GET["id"]);
    $vote = htmlspecialchars($_GET["vote"]);
    if ($vote == 'against' && get_user_class() < $againstoffer_class) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
    if ($vote =='yeah' || $vote =='against') {
        $userid = 0+$CURUSER["id"];
        $res = \NexusPHP\Components\Database::query("SELECT * FROM offervotes WHERE offerid=".\NexusPHP\Components\Database::escape($offerid)." AND userid=".\NexusPHP\Components\Database::escape($userid)) or sqlerr(__FILE__, __LINE__);
        $arr = mysqli_fetch_assoc($res);
        $voted = $arr;
        $offer_userid = \NexusPHP\Components\Database::single("offers", "userid", "WHERE id=".\NexusPHP\Components\Database::escape($offerid));
        if ($offer_userid == $CURUSER['id']) {
            stderr($lang_offers['std_error'], $lang_offers['std_cannot_vote_youself']);
        } elseif ($voted) {
            stderr($lang_offers['std_already_voted'], $lang_offers['std_already_voted_note']."<a  href=offers.php?id=$offerid&off_details=1>".$lang_offers['std_back_to_offer_detail'], false);
        } else {
            \NexusPHP\Components\Database::query("UPDATE offers SET $vote = $vote + 1 WHERE id=".\NexusPHP\Components\Database::escape($offerid)) or sqlerr(__FILE__, __LINE__);

            $res = \NexusPHP\Components\Database::query("SELECT users.username, offers.userid, offers.name FROM offers LEFT JOIN users ON offers.userid = users.id WHERE offers.id = ".\NexusPHP\Components\Database::escape($offerid)) or sqlerr(__FILE__, __LINE__);
            $arr = mysqli_fetch_assoc($res);

            $rs = \NexusPHP\Components\Database::query("SELECT yeah, against, allowed FROM offers WHERE id=".\NexusPHP\Components\Database::escape($offerid)) or sqlerr(__FILE__, __LINE__);
            $ya_arr = mysqli_fetch_assoc($rs);
            $yeah = $ya_arr["yeah"];
            $against = $ya_arr["against"];
            $finishtime = date("Y-m-d H:i:s");
            //allowed and send offer voted on message
            if (($yeah-$against)>=$minoffervotes && $ya_arr['allowed'] != "allowed") {
                if ($offeruptimeout_main) {
                    $timeouthour = floor($offeruptimeout_main/3600);
                    $timeoutnote = $lang_offers_target[get_user_lang($arr["userid"])]['msg_you_must_upload_in'].$timeouthour.$lang_offers_target[get_user_lang($arr["userid"])]['msg_hours_otherwise'];
                } else {
                    $timeoutnote = "";
                }
                \NexusPHP\Components\Database::query("UPDATE offers SET allowed='allowed', allowedtime=".\NexusPHP\Components\Database::escape($finishtime)." WHERE id=".\NexusPHP\Components\Database::escape($offerid)) or sqlerr(__FILE__, __LINE__);
                $msg = $lang_offers_target[get_user_lang($arr['userid'])]['msg_offer_voted_on']."[b][url=". get_protocol_prefix() . $BASEURL."/offers.php?id=$offerid&off_details=1]" . $arr[name] . "[/url][/b].". $lang_offers_target[get_user_lang($arr['userid'])]['msg_find_offer_option'].$timeoutnote;
                $subject = $lang_offers_target[get_user_lang($arr['userid'])]['msg_your_offer_allowed'];
                \NexusPHP\Components\Database::query("INSERT INTO messages (sender, receiver, added, msg, subject) VALUES(0, $arr[userid], " . \NexusPHP\Components\Database::escape(date("Y-m-d H:i:s")) . ", " . \NexusPHP\Components\Database::escape($msg) . ", ".\NexusPHP\Components\Database::escape($subject).")") or sqlerr(__FILE__, __LINE__);
                write_log("System allowed offer $arr[name]", 'normal');
            }
            //denied and send offer voted off message
            if (($against-$yeah)>=$minoffervotes && $ya_arr['allowed'] != "denied") {
                \NexusPHP\Components\Database::query("UPDATE offers SET allowed='denied' WHERE id=".\NexusPHP\Components\Database::escape($offerid)) or sqlerr(__FILE__, __LINE__);
                $msg = $lang_offers_target[get_user_lang($arr['userid'])]['msg_offer_voted_off']."[b][url=" . get_protocol_prefix() . $BASEURL."/offers.php?id=$offid&off_details=1]" . $arr[name] . "[/url][/b].".$lang_offers_target[get_user_lang($arr['userid'])]['msg_offer_deleted'] ;
                $subject = $lang_offers_target[get_user_lang($arr['userid'])]['msg_offer_deleted'];
                \NexusPHP\Components\Database::query("INSERT INTO messages (sender, receiver, added, msg, subject) VALUES(0, $arr[userid], " . \NexusPHP\Components\Database::escape(date("Y-m-d H:i:s")) . ", " . \NexusPHP\Components\Database::escape($msg) . ", ".\NexusPHP\Components\Database::escape($subject).")") or sqlerr(__FILE__, __LINE__);
                write_log("System denied offer $arr[name]", 'normal');
            }


            \NexusPHP\Components\Database::query("INSERT INTO offervotes (offerid, userid, vote) VALUES($offerid, $userid, ".\NexusPHP\Components\Database::escape($vote).")") or sqlerr(__FILE__, __LINE__);
            KPS("+", $offervote_bonus, $CURUSER["id"]);
            stdhead($lang_offers['head_vote_for_offer']);
            print("<h1 align=center>".$lang_offers['std_vote_accepted']."</h1>");
            print($lang_offers['std_vote_accepted_note']."<a  href=offers.php?id=$offerid&off_details=1>".$lang_offers['std_back_to_offer_detail']);
            stdfoot();
            die;
        }
    } else {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}
//=== end offer votes

//=== delete offer
if ($_GET["del_offer"]) {
    $del_offer = 0 + $_GET["del_offer"];
    if ($del_offer != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $offer = 0 + $_GET["id"];

    $userid = 0 + $CURUSER["id"];
    if (!is_valid_id($userid)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $res = \NexusPHP\Components\Database::query("SELECT * FROM offers WHERE id = $offer") or sqlerr(__FILE__, __LINE__);
    $num = mysqli_fetch_array($res);

    $name = $num["name"];

    if ($userid != $num["userid"] && get_user_class() < $offermanage_class) {
        stderr($lang_offers['std_error'], $lang_offers['std_cannot_delete_others_offer']);
    }

    if ($_GET["sure"]) {
        $sure = $_GET["sure"];
        if ($sure == '0' || $sure == '1') {
            $sure = 0 + $_GET["sure"];
        } else {
            stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
        }
    }


    if ($sure == 0) {
        stderr($lang_offers['std_delete_offer'], $lang_offers['std_delete_offer_note']."<br /><form method=post action=offers.php?id=$offer&del_offer=1&sure=1>".$lang_offers['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_offers['submit_confirm']."\"></form>", false);
    } elseif ($sure == 1) {
        $reason = $_POST["reason"];
        \NexusPHP\Components\Database::query("DELETE FROM offers WHERE id=$offer");
        \NexusPHP\Components\Database::query("DELETE FROM offervotes WHERE offerid=$offer");
        \NexusPHP\Components\Database::query("DELETE FROM comments WHERE offer=$offer");

        //===add karma	//=== use this if you use the karma mod
        //\NexusPHP\Components\Database::query("UPDATE users SET seedbonus = seedbonus-10.0 WHERE id = $num[userid]") or sqlerr(__FILE__, __LINE__);
        //===end

        if ($CURUSER["id"] != $num["userid"]) {
            $added = \NexusPHP\Components\Database::escape(date("Y-m-d H:i:s"));
            $subject = \NexusPHP\Components\Database::escape($lang_offers_target[get_user_lang($num["userid"])]['msg_offer_deleted']);
            $msg = \NexusPHP\Components\Database::escape($lang_offers_target[get_user_lang($num["userid"])]['msg_your_offer'].$num[name].$lang_offers_target[get_user_lang($num["userid"])]['msg_was_deleted_by']. "[url=userdetails.php?id=".$CURUSER['id']."]".$CURUSER['username']."[/url]".$lang_offers_target[get_user_lang($num["userid"])]['msg_blank'].($reason != "" ? $lang_offers_target[get_user_lang($num["userid"])]['msg_reason_is'].$reason : ""));
            \NexusPHP\Components\Database::query("INSERT INTO messages (sender, receiver, msg, added, subject) VALUES(0, $num[userid], $msg, $added, $subject)") or sqlerr(__FILE__, __LINE__);
        }
        write_log("Offer: $offer ($num[name]) was deleted by $CURUSER[username]".($reason != "" ? " (".$reason.")" : ""), 'normal');
        header("Refresh: 0; url=offers.php");
        die;
    } else {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}
//== end  delete offer

//=== prolly not needed, but what the hell... basically stopping the page getting screwed up
if ($_GET["sort"]) {
    $sort = $_GET["sort"];
    if ($sort == 'cat' || $sort == 'name' || $sort == 'added' || $sort == 'comments' || $sort == 'yeah' || $sort == 'against' || $sort == 'v_res') {
        $sort = $_GET["sort"];
    } else {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}
//=== end of prolly not needed, but what the hell :P

$categ = 0 + $_GET["category"];

if ($_GET["offerorid"]) {
    $offerorid = 0 + htmlspecialchars($_GET["offerorid"]);
    if (preg_match("/^[0-9]+$/", !$offerorid)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}

$search = ($_GET["search"]);

if ($search) {
    $search = " AND offers.name like ".\NexusPHP\Components\Database::escape("%$search%");
} else {
    $search = "";
}


$cat_order_type = "desc";
$name_order_type = "desc";
$added_order_type = "desc";
$comments_order_type = "desc";
$v_res_order_type = "desc";

/*
if ($cat_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $cat_order_type = "asc"; } // for torrent name
if ($name_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $name_order_type = "desc"; }
if ($added_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $added_order_type = "desc"; }
if ($comments_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $comments_order_type = "desc"; }
if ($v_res_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $v_res_order_type = "desc"; }
*/

if ($sort == "cat") {
    if ($_GET['type'] == "desc") {
        $cat_order_type = "asc";
    }
    $sort = " ORDER BY category ". $cat_order_type;
} elseif ($sort == "name") {
    if ($_GET['type'] == "desc") {
        $name_order_type = "asc";
    }
    $sort = " ORDER BY name ". $name_order_type;
} elseif ($sort == "added") {
    if ($_GET['type'] == "desc") {
        $added_order_type = "asc";
    }
    $sort = " ORDER BY added " . $added_order_type;
} elseif ($sort == "comments") {
    if ($_GET['type'] == "desc") {
        $comments_order_type = "asc";
    }
    $sort = " ORDER BY comments " . $comments_order_type;
} elseif ($sort == "v_res") {
    if ($_GET['type'] == "desc") {
        $v_res_order_type = "asc";
    }
    $sort = " ORDER BY (yeah - against) " . $v_res_order_type;
}




if ($offerorid <> null) {
    if (($categ <> null) && ($categ <> 0)) {
        $categ = "WHERE offers.category = " . $categ . " AND offers.userid = " . $offerorid;
    } else {
        $categ = "WHERE offers.userid = " . $offerorid;
    }
} elseif ($categ == 0) {
    $categ = '';
} else {
    $categ = "WHERE offers.category = " . $categ;
}

$res = \NexusPHP\Components\Database::query("SELECT count(offers.id) FROM offers inner join categories on offers.category = categories.id inner join users on offers.userid = users.id  $categ $search") or sqlerr(__FILE__, __LINE__);
$row = mysqli_fetch_array($res);
$count = $row[0];

$perpage = 25;

list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, $_SERVER["PHP_SELF"] ."?" . "category=" . $_GET["category"] . "&sort=" . $_GET["sort"] . "&");

//stderr("", $sort);
if ($sort == "") {
    $sort =  "ORDER BY added desc ";
}

$res = \NexusPHP\Components\Database::query("SELECT offers.id, offers.userid, offers.name, offers.added, offers.allowedtime, offers.comments, offers.yeah, offers.against, offers.category as cat_id, offers.allowed, categories.image, categories.name as cat FROM offers inner join categories on offers.category = categories.id $categ $search $sort $limit") or sqlerr(__FILE__, __LINE__);
$num = mysqli_num_rows($res);

stdhead($lang_offers['head_offers']);
begin_main_frame();
begin_frame($lang_offers['text_offers_section'], true, 10, "100%", "center");

print("<p align=\"left\"><b><font size=\"5\">".$lang_offers['text_rules']."</font></b></p>\n");
print("<div align=\"left\"><ul>");
print("<li>".$lang_offers['text_rule_one_one'].get_user_class_name($upload_class, false, true, true).$lang_offers['text_rule_one_two'].get_user_class_name($addoffer_class, false, true, true).$lang_offers['text_rule_one_three']."</li>\n");
print("<li>".$lang_offers['text_rule_two_one']."<b>".$minoffervotes."</b>".$lang_offers['text_rule_two_two']."</li>\n");
if ($offervotetimeout_main) {
    print("<li>".$lang_offers['text_rule_three_one']."<b>".($offervotetimeout_main / 3600)."</b>".$lang_offers['text_rule_three_two']."</li>\n");
}
if ($offeruptimeout_main) {
    print("<li>".$lang_offers['text_rule_four_one']."<b>".($offeruptimeout_main / 3600)."</b>".$lang_offers['text_rule_four_two']."</li>\n");
}
print("</ul></div>");
if (get_user_class() >= $addoffer_class) {
    print("<div align=\"center\" style=\"margin-bottom: 8px;\"><a href=\"?add_offer=1\">".
"<b>".$lang_offers['text_add_offer']."</b></a></div>");
}
print("<div align=\"center\"><form method=\"get\" action=\"?\">".$lang_offers['text_search_offers']."&nbsp;&nbsp;<input type=\"text\" id=\"specialboxg\" name=\"search\" />&nbsp;&nbsp;");
$cats = genrelist($browsecatmode);
$catdropdown = "";
foreach ($cats as $cat) {
    $catdropdown .= "<option value=\"" . $cat["id"] . "\"";
    $catdropdown .= ">" . htmlspecialchars($cat["name"]) . "</option>\n";
}
print("<select name=\"category\"><option value=\"0\">".$lang_offers['select_show_all']."</option>".$catdropdown."</select>&nbsp;&nbsp;<input type=\"submit\" class=\"btn\" value=\"".$lang_offers['submit_search']."\" /></form></div>");
end_frame();
print("<br /><br />");

$last_offer = strtotime($CURUSER['last_offer']);
if (!$num) {
    stdmsg($lang_offers['text_nothing_found'], $lang_offers['text_nothing_found']);
} else {
    $catid = $_GET[category];
    print("<table class=\"torrents\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\">");
    print("<tr><td class=\"colhead\" style=\"padding: 0px\"><a href=\"?category=" . $catid . "&amp;sort=cat&amp;type=".$cat_order_type."\">".$lang_offers['col_type']."</a></td>".
"<td class=\"colhead\" width=\"100%\"><a href=\"?category=" . $catid . "&amp;sort=name&amp;type=".$name_order_type."\">".$lang_offers['col_title']."</a></td>".
"<td colspan=\"3\" class=\"colhead\"><a href=\"?category=" . $catid . "&amp;sort=v_res&amp;type=".$v_res_order_type."\">".$lang_offers['col_vote_results']."</a></td>".
"<td class=\"colhead\"><a href=\"?category=" . $catid . "&amp;sort=comments&amp;type=".$comments_order_type."\"><img class=\"comments\" src=\"pic/trans.gif\" alt=\"comments\" title=\"".$lang_offers['title_comment']."\" />".$lang_offers['col_comment']."</a></td>".
"<td class=\"colhead\"><a href=\"?category=" . $catid . "&amp;sort=added&amp;type=".$added_order_type."\"><img class=\"time\" src=\"pic/trans.gif\" alt=\"time\" title=\"".$lang_offers['title_time_added']."\" /></a></td>");
    if ($offervotetimeout_main > 0 && $offeruptimeout_main > 0) {
        print("<td class=\"colhead\">".$lang_offers['col_timeout']."</td>");
    }
    print("<td class=\"colhead\">".$lang_offers['col_offered_by']."</td>".
(get_user_class() >= $offermanage_class ? "<td class=\"colhead\">".$lang_offers['col_act']."</td>" : "")."</tr>\n");
    for ($i = 0; $i < $num; ++$i) {
        $arr = mysqli_fetch_assoc($res);


        $addedby = get_username($arr['userid']);
        $comms = $arr['comments'];
        if ($comms == 0) {
            $comment = "<a href=\"comment.php?action=add&amp;pid=".$arr[id]."&amp;type=offer\" title=\"".$lang_offers['title_add_comments']."\">0</a>";
        } else {
            if (!$lastcom = $Cache->get_value('offer_'.$arr[id].'_last_comment_content')) {
                $res2 = \NexusPHP\Components\Database::query("SELECT user, added, text FROM comments WHERE offer = $arr[id] ORDER BY added DESC LIMIT 1");
                $lastcom = mysqli_fetch_array($res2);
                $Cache->cache_value('offer_'.$arr[id].'_last_comment_content', $lastcom, 1855);
            }
            $timestamp = strtotime($lastcom["added"]);
            $hasnewcom = ($lastcom['user'] != $CURUSER['id'] && $timestamp >= $last_offer);
            if ($CURUSER['showlastcom'] != 'no') {
                if ($lastcom) {
                    $title = "";
                    if ($CURUSER['timetype'] != 'timealive') {
                        $lastcomtime = $lang_offers['text_at_time'].$lastcom['added'];
                    } else {
                        $lastcomtime = $lang_offers['text_blank'].gettime($lastcom["added"], true, false, true);
                    }
                    $counter = $i;
                    $lastcom_tooltip[$counter]['id'] = "lastcom_" . $counter;
                    $lastcom_tooltip[$counter]['content'] = ($hasnewcom ? "<b>(<font class='new'>".$lang_offers['text_new']."</font>)</b> " : "").$lang_offers['text_last_commented_by'].get_username($lastcom['user']) . $lastcomtime."<br />". format_comment(mb_substr($lastcom['text'], 0, 100, "UTF-8") . (mb_strlen($lastcom['text'], "UTF-8") > 100 ? " ......" : ""), true, false, false, true, 600, false, false);
                    $onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('" . $lastcom_tooltip[$counter]['id'] . "'), 'trail', false, 'delay', 500,'lifetime',3000,'fade','both','styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
                }
            } else {
                $title = " title=\"".($hasnewcom ? $lang_offers['title_has_new_comment'] : $lang_offers['title_no_new_comment'])."\"";
                $onmouseover = "";
            }
            $comment = "<b><a".$title." href=\"?id=".$arr[id]."&amp;off_details=1#startcomments\" ".$onmouseover.">".($hasnewcom ? "<font class='new'>" : ""). $comms .($hasnewcom ? "</font>" : "")."</a></b>";
        }

        //==== if you want allow deny for offers use this next bit
        if ($arr["allowed"] == 'allowed') {
            $allowed = "&nbsp;<b>[<font color=\"green\">".$lang_offers['text_allowed']."</font>]</b>";
        } elseif ($arr["allowed"] == 'denied') {
            $allowed = "&nbsp;<b>[<font color=\"red\">".$lang_offers['text_denied']."</font>]</b>";
        } else {
            $allowed = "&nbsp;<b>[<font color=\"orange\">".$lang_offers['text_pending']."</font>]</b>";
        }
        //===end

        if ($arr["yeah"] == 0) {
            $zvote = $arr[yeah];
        } else {
            $zvote = "<b><a href=\"?id=".$arr[id]."&amp;offer_vote=1\">".$arr[yeah]."</a></b>";
        }
        if ($arr["against"] == 0) {
            $pvote = "$arr[against]";
        } else {
            $pvote = "<b><a href=\"?id=".$arr[id]."&amp;offer_vote=1\">".$arr[against]."</a></b>";
        }

        if ($arr["yeah"] == 0 && $arr["against"] == 0) {
            $v_res = "0";
        } else {
            $v_res = "<b><a href=\"?id=".$arr[id]."&amp;offer_vote=1\" title=\"".$lang_offers['title_show_vote_details']."\"><font color=\"green\">" .$arr[yeah]."</font> - <font color=\"red\">".$arr[against]."</font> = ".($arr[yeah] - $arr[against]). "</a></b>";
        }
        $addtime = gettime($arr['added'], false, true);
        $dispname = $arr[name];
        $count_dispname=mb_strlen($arr[name], "UTF-8");
        $max_length_of_offer_name = 70;
        if ($count_dispname > $max_length_of_offer_name) {
            $dispname=mb_substr($dispname, 0, $max_length_of_offer_name-2, "UTF-8") . "..";
        }
        print("<tr><td class=\"rowfollow\" style=\"padding: 0px\"><a href=\"?category=".$arr['cat_id']."\">".return_category_image($arr['cat_id'], "")."</a></td><td style='text-align: left'><a href=\"?id=".$arr[id]."&amp;off_details=1\" title=\"".htmlspecialchars($arr[name])."\"><b>".htmlspecialchars($dispname)."</b></a>".($CURUSER['appendnew'] != 'no' && strtotime($arr["added"]) >= $last_offer ? "<b> (<font class='new'>".$lang_offers['text_new']."</font>)</b>" : "").$allowed."</td><td class=\"rowfollow nowrap\" style='padding: 5px' align=\"center\">".$v_res."</td><td class=\"rowfollow nowrap\" ".(get_user_class() < $againstoffer_class ? " colspan=\"2\" " : "")." style='padding: 5px'><a href=\"?id=".$arr[id]."&amp;vote=yeah\" title=\"".$lang_offers['title_i_want_this']."\"><font color=\"green\"><b>".$lang_offers['text_yep']."</b></font></a></td>".(get_user_class() >= $againstoffer_class ? "<td class=\"rowfollow nowrap\" align=\"center\"><a href=\"?id=".$arr[id]."&amp;vote=against\" title=\"".$lang_offers['title_do_not_want_it']."\"><font color=\"red\"><b>".$lang_offers['text_nah']."</b></font></a></td>" : ""));

        print("<td class=\"rowfollow\">".$comment."</td><td class=\"rowfollow nowrap\">" . $addtime. "</td>");
        if ($offervotetimeout_main > 0 && $offeruptimeout_main > 0) {
            if ($arr["allowed"] == 'allowed') {
                $futuretime = strtotime($arr['allowedtime']) + $offeruptimeout_main;
                $timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, true, true, false, true);
            } elseif ($arr["allowed"] == 'pending') {
                $futuretime = strtotime($arr['added']) + $offervotetimeout_main;
                $timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, true, true, false, true);
            }
            if (!$timeout) {
                $timeout = "N/A";
            }
            print("<td class=\"rowfollow nowrap\">".$timeout."</td>");
        }
        print("<td class=\"rowfollow\">".$addedby."</td>".(get_user_class() >= $offermanage_class ? "<td class=\"rowfollow\"><a href=\"?id=".$arr[id]."&amp;del_offer=1\"><img class=\"staff_delete\" src=\"pic/trans.gif\" alt=\"D\" title=\"".$lang_offers['title_delete']."\" /></a><br /><a href=\"?id=".$arr[id]."&amp;edit_offer=1\"><img class=\"staff_edit\" src=\"pic/trans.gif\" alt=\"E\" title=\"".$lang_offers['title_edit']."\" /></a></td>" : "")."</tr>");
    }
    print("</table>\n");
    echo $pagerbottom;
    if (!isset($CURUSER) || $CURUSER['showlastcom'] == 'yes') {
        create_tooltip_container($lastcom_tooltip, 400);
    }
}
end_main_frame();
$USERUPDATESET[] = "last_offer = ".\NexusPHP\Components\Database::escape(date("Y-m-d H:i:s"));
stdfoot();
