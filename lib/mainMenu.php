<div class="wrap wordfence">
	<h2>Wordfence Assistant</h2>
	<div class="wfAstBlock">
	<h3>Why does this additional plugin exist?</h3>
	<p>
		In rare cases, Wordfence users can accidentally lock themselves out of their system. 
		Wordfence provides a built-in user-friendly system to regain access to your website which allows site 
		administrators to send themselves an unlock email which contains a link that unlocks their website.
		<br /><br />
		Because Wordfence has become so popular, we see edge cases where systems administrators no longer
		have access to their old email address or the email unlock does not work for another reason. 
		To help unlock sites with that problem, we've provided this plugin which you can install
		after you've removed Wordfence from your system. You can use this plugin to modify 
		the Wordfence data in your database and disable the Wordfence firewall so that if you
		reinstall Wordfence the firewall won't lock you out again. You can also use this plugin
		to delete all Wordfence data.
	</p>
	<h3>Disable Wordfence Firewall</h3>
	<p>
		If you have locked yourself out of your website and the "recovery email" option does not work, 
		you can regain access by deleting the Wordfence files from your system. These files are stored in<br /><br />
		<b>/wp-content/plugins/wordfence/</b>
		<br /><br />
		Once you've done that you might want to reenable Wordfence but if you do you could get locked out again
		without having the opportunity to modify your Wordfence configuration and fix the thing that locked
		you out in the first place.
		<br /><br />
		You can use the button below to disable the Wordfence firewall. Then when you reinstall
		the Wordfence files and activate Wordfence you won't be locked out any longer. You can then 
		access the Wordfence Firewall page and modify the configuration to make sure you don't get locked out again.
		<br /><br />
		<input class="button-primary" type="button" name="but1" value="Disable Wordfence Firewall" onclick="WFAST.disableFirewall(); return ;" />
	</p>
	<h3>Remove all Wordfence Data in the Database and elsewhere</h3>
	<p>
		Use this option if you've uninstalled Wordfence and don't plan to reinstall it. It will remove all Wordfence tables from your database
		and clear any other data we may store in the system including scheduled jobs. Note that this does not delete the Wordfence files from your system.
		<br /><br />
		<input class="button-primary" type="button" name="but1" value="Delete all Wordfence Data and Tables" onclick="WFAST.delAll(); return false;" />
	</p>
	<h3>Clear all locked out Wordfence IP's, locked out users and advanced blocks</h3>
	<p>
		If for some reason you can't disable the Firewall in Wordfence, you can use this option to clear all tables that contain locked out IP addresses,
		locked out users and rules that may cause you to be locked out. 
		<br /><br />
		<input class="button-primary" type="button" name="but1" value="Clear all Wordfence locked out IPs, locked out Users and advanced blocks" onclick="WFAST.clearLocks();" />
	</p>
	<h3>Clear the Wordfence Live Traffic Table</h3>
	<p>
		Some users have requested the ability to manually purge the Wordfence Live Traffic table. The table is pruned
		automatically by Wordfence from time to time, but clicking this button will do that for you. 
		<br /><br />
		<input class="button-primary" type="button" name="but1" value="Delete all Live Traffic Data" onclick="WFAST.clearLiveTraffic(); return;" />
	</p>
	</div>

</div>

