<?php

/*
  +----------------------------------------------------------------------+
  | The PECL website                                                     |
  +----------------------------------------------------------------------+
  | Copyright (c) 1999-2019 The PHP Group                                |
  +----------------------------------------------------------------------+
  | This source file is subject to version 3.01 of the PHP license,      |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | https://php.net/license/3_01.txt                                     |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Authors: Martin Jansen <mj@php.net>                                  |
  +----------------------------------------------------------------------+
*/

use App\BorderBox;
use App\Entity\Note;
use App\Entity\User as UserEntity;
use App\User;

$auth->secure(true);

if (!empty($_GET['phpinfo'])) {
    phpinfo();
    exit();
}

$acreq = isset($_GET['acreq']) ? strip_tags(htmlspecialchars($_GET['acreq'], ENT_QUOTES)) : null;

$SIDEBAR_DATA='
<noscript><p>
<!-- be annoying! -->
<b><blink>You must enable Javascript to use this page!</blink></b>
</p></noscript>
';

response_header("Administration");

echo '<h1>PECL Administration</h1>';
echo '<p>This is the PECL administration page.</p>';
echo '<a href="/admin/package-maintainers.php" class="item">Package maintainers</a>';
echo '<br>';
echo '<a href="/admin/category-manager.php" class="item">Manage categories</a>';
echo '<hr>';

$note = new Note();
$note->setDatabase($database);
$note->setAuthUser($auth_user);

// adding and deleting notes
if (!empty($_REQUEST['cmd'])) {
    if ($_REQUEST['cmd'] == "Add note" && !empty($_REQUEST['note']) && !empty($_REQUEST['key']) && !empty($_REQUEST['id'])) {
        $note->add($_REQUEST['key'], $_REQUEST['id'], $_REQUEST['note']);
        unset($_REQUEST['cmd']);
    } elseif ($_REQUEST['cmd'] == "Delete note" && !empty($_REQUEST['id'])) {
        // Delete note
        $note->remove($_REQUEST['id']);
    } elseif ($_REQUEST['cmd'] == "Open Account" && !empty($_REQUEST['uid'])) {
        /**
         * Open account
         */

        // another hack to remove the temporary "purpose" field
        // from the user's "userinfo"
        if (User::activate($_REQUEST['uid'])) {
            print '<p>Opened account '.htmlspecialchars($_REQUEST['uid'], ENT_QUOTES)."...</p>\n";
        }

    } elseif ($_REQUEST['cmd'] == "Reject Request" && !empty($_REQUEST['uid'])) {
        /**
         * Reject account request
         */
        if (is_array($_REQUEST['uid'])) {
            foreach ($_REQUEST['uid'] as $uid) {
                User::rejectRequest($uid, $_REQUEST['reason']);
                echo 'Account rejected: ' . $uid . '<br />';
            }

        } elseif (User::rejectRequest($_REQUEST['uid'], $_REQUEST['reason'])) {
            print '<p>Rejected account request for '.htmlspecialchars($_REQUEST['uid'], ENT_QUOTES)."...</p>\n";
        }

    } elseif ($_REQUEST['cmd'] == "Delete Request" && !empty($_REQUEST['uid'])) {
        /**
         * Delete account request
         */
        if (is_array($_REQUEST['uid'])) {
            foreach ($_REQUEST['uid'] as $uid) {
                User::remove($uid);
                echo 'Account request deleted: ' . $uid . '<br />';
            }


        } elseif (User::remove($_REQUEST['uid'])) {
            print '<p>Deleted account request for "'.htmlspecialchars($_REQUEST['uid'], ENT_QUOTES). '"...</p>';
        }
    }
}

?>
<script>
function confirmed_goto(url, message) {
    if (confirm(message)) {
        location = url;
    }
}

function confirmed_submit(button, action, required, errormsg) {
    if (required && required.value == '') {
        alert(errormsg);
        return;
    }
    if (confirm('Are you sure you want to ' + action + '?')) {
        button.form.cmd.value = button.value;
        button.form.submit();
    }
}

function updateRejectReason(selectObj) {
    if (selectObj.selectedIndex != 0) {
        document.forms['account_form'].reason.value = selectObj.options[selectObj.selectedIndex].value;
    }
    selectObj.selectedIndex = 0;
}
</script>
<?php

do {
    // approve account request form
    if (!empty($acreq)) {
        $requser = new UserEntity($database, $acreq);

        if (empty($requser->get('name'))) {
            break;
        }

        list($purpose, $moreinfo) = @unserialize($requser->get('userinfo'));

        $bb = new BorderBox("Account request from " . htmlspecialchars($requser->get('name'), ENT_QUOTES)
              . "&lt;" . htmlspecialchars($requser->get('email'), ENT_QUOTES) . "&gt;", "100%", "", 2, true);
        $bb->horizHeadRow("Requested username:", htmlspecialchars($requser->get('handle'), ENT_QUOTES));
        $bb->horizHeadRow("Realname:", htmlspecialchars($requser->get('name'), ENT_QUOTES));
        $bb->horizHeadRow("Email address:", "<a href=\"mailto:" . htmlspecialchars($requser->get('email'), ENT_QUOTES) . "\">" .
                  htmlspecialchars($requser->get('email'), ENT_QUOTES) . "</a>");
        $bb->horizHeadRow("Purpose of account:", nl2br(htmlspecialchars($purpose, ENT_QUOTES)));
        $bb->horizHeadRow("More information:", htmlspecialchars($moreinfo, ENT_QUOTES));
        $bb->end();

        print "<br />\n";
        $bb = new BorderBox("Notes for user " . htmlspecialchars($requser->handle, ENT_QUOTES));

        $sql = "SELECT id, nby, UNIX_TIMESTAMP(ntime) AS ntime, note
                FROM notes WHERE uid = ? ORDER BY ntime
        ";
        $notes = $database->run($sql, [$requser->handle])->fetchAll();

        $i = "      ";
        if (is_array($notes) && count($notes) > 0) {
            print "$i<table cellpadding=\"2\" cellspacing=\"0\" border=\"0\">\n";

            foreach ($notes as $data) {
                $id = $data['id'];
                $nby = $data['nby'];
                $ntime = $data['ntime'];
                $note = $data['note'];

                print "$i <tr>\n";
                print "$i  <td>\n";
                print "$i   <b>$nby " . date('H:i jS F Y', $ntime) . ":</b>";

                if ($nby == $auth_user->handle) {
                    $url = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'?acreq='.$acreq.'&cmd=Delete+note&id='.$id;
                    $msg = "Are you sure you want to delete this note?";
                    print "[<a href=\"javascript:confirmed_goto('$url', '$msg')\">delete your note</a>]";
                }

                print "<br />\n";
                print "$i   ".htmlspecialchars($note, ENT_QUOTES)."\n";
                print "$i  </td>\n";
                print "$i </tr>\n";
                print "$i <tr><td>&nbsp;</td></tr>\n";
            }

            print "$i</table>\n";
        } else {
            print "No notes.";
        }
        print "$i<form action=\"" . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) . "\" method=\"POST\">\n";
        print "$i<table cellpadding=\"2\" cellspacing=\"0\" border=\"0\">\n";
        print "$i <tr>\n";
        print "$i  <td>\n";
        print "$i   To add a note, enter it here:<br />\n";
        print "$i    <textarea rows=\"3\" cols=\"55\" name=\"note\"></textarea><br />\n";
        print "$i   <input type=\"submit\" value=\"Add note\" name=\"cmd\" />\n";
        print "$i   <input type=\"hidden\" name=\"key\" value=\"uid\" />\n";
        print "$i   <input type=\"hidden\" name=\"id\" value=\"$requser->handle\" />\n";
        print "$i   <input type=\"hidden\" name=\"acreq\" value=\"$acreq\" />\n";
        print "$i  </td>\n";
        print "$i </tr>\n";
        print "$i</table>\n";
        print "$i</form>\n";

        $bb->end();
?>

<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>" method="POST" name="account_form">
<input type="hidden" name="cmd" value="" />
<input type="hidden" name="uid" value="<?php echo $requser->handle; ?>" />
<table cellpadding="3" cellspacing="0" border="0" width="90%">
 <tr>
  <td align="center"><input type="button" value="Open Account" onclick="confirmed_submit(this, 'open this account')" /></td>
  <td align="center"><input type="button" value="Reject Request" onclick="confirmed_submit(this, 'reject this request', this.form.reason, 'You must give a reason for rejecting the request.')" /></td>
  <td align="center"><input type="button" value="Delete Request" onclick="confirmed_submit(this, 'delete this request')" /></td>
 </tr>
 <tr>
  <td colspan="3">
   If dismissing an account request, enter the reason here
   (will be emailed to <?php echo $requser->get('email'); ?>):<br />
   <textarea rows="3" cols="60" name="reason"></textarea><br />

    <select onchange="return updateRejectReason(this)">
           <option>Select reason...</option>
           <option value="You don't need a PECL account to use PECL or PECL packages.">You don't need a PECL account to use PECL or PECL packages.</option>
        <option value="Please propose all new packages to the mailing list pecl-dev@lists.php.net first.">Please propose all new packages to the mailing list pecl-dev@lists.php.net first.</option>
        <option value="Please send all bug fixes to the mailing list pecl-dev@lists.php.net and post a bug at the pecl.php.net package homepage.">Please send all bug fixes to the mailing list pecl-dev@lists.php.net.</option>
        <option value="Please supply valid credentials, including your full name and a descriptive reason for an account.">Please supply valid credentials, including your full name and a descriptive reason for an account.</option>
   </select>

  </td>
</table>
</form>

<?php
    // admin menu
    } else {
        ?>
        <script>
            // This code is *nasty* (nastyCode™)
            function highlightAccountRow(spanObj)
            {
                return true;
                var highlightColor = '#cfffb7';

                if (typeof(arguments[1]) == 'undefined') {
                    action = (spanObj.parentNode.parentNode.childNodes[0].style.backgroundColor == highlightColor);
                } else {
                    action = !arguments[1];
                }

                if (document.getElementById) {
                    for (var i=0; i<spanObj.parentNode.parentNode.childNodes.length; i++) {
                        if (action) {
                            spanObj.parentNode.parentNode.childNodes[i].style.backgroundColor = '#ffffff';
                            spanObj.parentNode.parentNode.childNodes[0].childNodes[0].checked = false;
                        } else {
                            spanObj.parentNode.parentNode.childNodes[i].style.backgroundColor = highlightColor;
                            spanObj.parentNode.parentNode.childNodes[0].childNodes[0].checked = true;
                        }
                    }
                }
            }

            allSelected = false;

            function toggleSelectAll(linkElement)
            {
                tableBodyElement = linkElement.parentNode.parentNode.parentNode.parentNode;

                for (var i=0; i<tableBodyElement.childNodes.length; i++) {
                    if (tableBodyElement.childNodes[i].childNodes[0].childNodes[0].tagName == 'INPUT') {
                        highlightAccountRow(tableBodyElement.childNodes[i].childNodes[1].childNodes[0], !allSelected);
                    }
                }

                allSelected = !allSelected;
            }

            function setCmdInput(mode)
            {
                switch (mode) {
                    case 'reject':
                        if (document.forms['mass_reject_form'].reason.selectedIndex == 0) {
                            alert('Please select a reason to reject the accounts!');

                        } else if (confirm('Are you sure you want to reject these account requests ?')) {
                            document.forms['mass_reject_form'].cmd.value = 'Reject Request';
                            return true;
                        }
                        break;

                    case 'delete':
                        if (confirm('Are you sure you want to delete these account requests ?')) {
                            document.forms['mass_reject_form'].cmd.value = 'Delete Request';
                            return true;
                        }
                        break;
                }

                return false;
            }
        </script>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>" name="mass_reject_form" method="post">
        <input type="hidden" value="" name="cmd"/>
        <?php
        $bb = new BorderBox("Account Requests", "100%", "", 7, true);

        $sql = "SELECT u.handle,u.name,n.note,u.userinfo,u.created
                FROM users u
                LEFT JOIN notes n ON n.uid = u.handle
                WHERE u.registered = 0
                ORDER BY created ASC
        ";

        $requests = $database->run($sql)->fetchAll();

        if (is_array($requests) && count($requests) > 0) {
            $bb->headRow("<font face=\"Marlett\"><a href=\"#\" onclick=\"toggleSelectAll(this)\">6</a></font>", "Name", "Handle", "Account Purpose", "Status", "Created at", "&nbsp;");

            foreach ($requests as $data) {
                $handle = $data['handle'];
                $name = $data['name'];
                $note = $data['note'];
                $userinfo = $data['userinfo'];
                $created_at = $data['created'];

                // Grab userinfo/request purpose
                if (@unserialize($userinfo)) {
                    $userinfo = @unserialize($userinfo);
                    $account_purpose = $userinfo[0];
                } else {
                    $account_purpose = $userinfo;
                }

                $rejected = (preg_match("/^Account rejected:/", $note));
                if ($rejected) {
                    continue;
                }
                $bb->plainRow('<input type="checkbox" value="' . $handle . '" name="uid[]" onmousedown="highlightAccountRow(this)"/>',
                              sprintf('<span style="cursor: hand" onmousedown="highlightAccountRow(this)">%s</span>', $name),
                              sprintf('<span style="cursor: hand" onmousedown="highlightAccountRow(this)">%s</span>', $handle),
                              sprintf('<span style="cursor: hand" onmousedown="highlightAccountRow(this)">%s</span>', nl2br($account_purpose)),
                              sprintf('<span style="cursor: hand" onmousedown="highlightAccountRow(this)">%s</span>', ($rejected ? "rejected" : "<font color=\"#c00000\"><strong>Outstanding</strong></font>")),
                              sprintf('<span style="cursor: hand" onmousedown="highlightAccountRow(this)">%s</span>', $created_at),
                              sprintf('<span style="cursor: hand" onmousedown="highlightAccountRow(this)">%s</span>', '<a onmousedown="event.cancelBubble = true" href="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES).'?acreq='.htmlspecialchars($handle, ENT_QUOTES).'"><img src="/img/edit.gif" alt="Edit"></a>')
                              );
            }

        } else {
            print "No account requests.";
        }
        $bb->end();

        ?>
        <br />
        <table align="center">
        <tr>
            <td>
                <select name="reason">
                    <option value="">Select rejection reason...</option>
                    <option value="Account not needed">Account not needed</option>
                </select>
            </td>
            <td><input type="submit" value="Reject selected accounts" onclick="return setCmdInput('reject')" /></td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td><input type="submit" value="Delete selected accounts" onclick="return setCmdInput('delete')" /></td>
        </tr>
        </table>

        </form>
<?php
    }
} while (false);

response_footer();
