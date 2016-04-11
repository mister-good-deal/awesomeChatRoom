<?php

use \classes\managers\UserManager as UserManager;

// $userManager = new UserManager(isset($_SESSION['user']) ? unserialize($_SESSION['user']) : null);

// if ($userManager->hasKibanaRight()) {

?>

<div class="page" data-url="kibana" data-title="awesomeChatRoom - Kibana">
    <iframe src="http://awesomechatroom.dev:8080/kibana/index" id="kibana-iframe" frameBorder="0" seamless></iframe>
</div>

<?php

// }

?>
