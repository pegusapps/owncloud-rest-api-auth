<form class="section" id="rest_auth_app" action="/index.php/apps/rest_auth_app" method="post">
	<h2>REST Authentication</h2>
	<h3>Account</h3>
	<p>
		<label style="display: inline-block; width: 250px" for="rest_auth_api_url">API URL</label>
		<input type="text" id="rest_auth_api_url"
					 style="width: 250px;"
					 name="rest_auth_api_url"
					 value="<?php p($_['rest_auth_api_url']); ?>"/>
	</p>
	<p>
		<label style="display: inline-block; width: 250px" for="rest_auth_api_access_key">API Access Key</label>
		<input type="text" id="rest_auth_api_access_key" name="rest_auth_api_access_key"
					 style="width: 250px;"
					 value="<?php p($_['rest_auth_api_access_key']); ?>"
		/>
	</p>
    <?php
    if ($_['rest_connection_error']) {
        ?>
			<p style="color: #ff627a">
				There is a problem connecting to the REST API. Please check your settings and save them to retry.
			</p>
        <?php
    }
    ?>

    <?php
    if ($_['tag_count'] > 0) {
        ?>
			<h3>Tag To Group Mapping</h3>
        <?php
    }
    ?>

    <?php for ($i = 0; $i < $_['tag_count']; $i++) {
        if (!empty($_["tag-$i-original"])) { ?>
					<p>
						<label style="display: inline-block; width: 250px"><?php p($_["tag-$i-original"]); ?></label>
						<input style="width: 250px;" type="text" id="<?php p("tag-$i"); ?>" name="tags[<?php p("tag-$i"); ?>]"
									 value="<?php p($_["tag-$i"]); ?>"/>
					</p>
        <?php }
    } ?>
	<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>" id="requesttoken">
	<input type="submit" value="Save"/>
</form>
