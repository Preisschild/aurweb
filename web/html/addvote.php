<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../lib');

include_once("aur.inc.php");
set_lang();
check_sid();

$title = __("Add Proposal");

html_header($title);

if (isset($_COOKIE["AURSID"])) {
  $atype = account_from_sid($_COOKIE["AURSID"]);
  $uid = uid_from_sid($_COOKIE["AURSID"]);
} else {
  $atype = "";
}

if ($atype == "Trusted User" || $atype == "Developer") {

	if (!empty($_POST['addVote']) && !check_token()) {
		$error = __("Invalid token for user action.");
	}

	if (!empty($_POST['addVote']) && check_token()) {
		$error = "";

		if (!empty($_POST['user'])) {
			if (!valid_user($_POST['user'])) {
				$error.= __("Username does not exist.");
			} else {

				if (open_user_proposals($_POST['user'])) {
					$error.= __("%s already has proposal running for them.", htmlentities($_POST['user']));
				}
			}
		}

		if (!empty($_POST['length'])) {
			if (!is_numeric($_POST['length'])) {
				$error.=  __("Length must be a number.") ;
			} else if ($_POST['length'] < 1) {
				$error.= __("Length must be at least 1.");
			} else {
				$len = (60*60*24)*$_POST['length'];
			}
		} else {
			$len = 60*60*24*7;
		}

		if (empty($_POST['agenda'])) {
			$error.= __("Proposal cannot be empty.");
		}
	}

	if (!empty($_POST['addVote']) && empty($error)) {
		add_tu_proposal($_POST['agenda'], $_POST['user'], $len, $uid);

		print "<p class=\"pkgoutput\">" . __("New proposal submitted.") . "</p>\n";
	} else {
?>

<?php if (!empty($error)): ?>
	<p style="color: red;" class="pkgoutput"><?= $error ?></p>
<?php endif; ?>

<div class="box">
	<h2><?= __("Submit a proposal to vote on.") ?></h2>

	<form action="<?= get_uri('/addvote/'); ?>" method="post">
		<p>
			<label for="id_user"><?= __("Applicant/TU") ?></label>
			<input type="text" name="user" id="id_user" value="<?php if (!empty($_POST['user'])) { print htmlentities($_POST['user'], ENT_QUOTES); } ?>" />
			<?= __("(empty if not applicable)") ?>
		</p>
		<p>
			<label for="id_length"><?= __("Length in days") ?></label>
			<input type="text" name="length" id="id_length" value="<?php if (!empty($_POST['length'])) { print htmlentities($_POST['length'], ENT_QUOTES); } ?>" />
			<?= __("(defaults to 7 if empty)") ?>
		</p>
		<p>
		<label for="id_agenda"><?= __("Proposal") ?></label><br />
		<textarea name="agenda" id="id_agenda" rows="15" cols="80"><?php if (!empty($_POST['agenda'])) { print htmlentities($_POST['agenda']); } ?></textarea><br />
		<input type="hidden" name="addVote" value="1" />
		<input type="hidden" name="token" value="<?= htmlspecialchars($_COOKIE['AURSID']) ?>" />
		<input type="submit" class="button" value="<?= __("Submit"); ?>" />
		</p>
	</form>
</div>
<?php
	}
} else {
	print __("You are not allowed to access this area.");
}

html_footer(AUR_VERSION);

