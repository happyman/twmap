<?php
$ha3a = array(
//'Hybrid/thirdparty/Facebook/facebook.php',
//'Hybrid/thirdparty/Facebook/base_facebook.php',
'Hybrid/thirdparty/Facebook/autoload.php',
'Hybrid/Auth.php',
'Hybrid/Endpoint.php',
'Hybrid/Error.php',
'Hybrid/Exception.php',
'Hybrid/Logger.php',
'Hybrid/Provider_Adapter.php',
'Hybrid/Provider_Model.php',
'Hybrid/Provider_Model_OAuth1.php',
'Hybrid/Provider_Model_OAuth2.php',
'Hybrid/Provider_Model_OpenID.php',
'Hybrid/Providers/AOL.php',
'Hybrid/Providers/Facebook.php',
'Hybrid/Providers/Foursquare.php',
'Hybrid/Providers/Google.php',
'Hybrid/Providers/LinkedIn.php',
'Hybrid/Providers/Live.php',
'Hybrid/Providers/OpenID.php',
'Hybrid/Providers/Twitter.php',
'Hybrid/Providers/Xuite.php',
'Hybrid/Providers/Yahoo.php',
'Hybrid/Storage.php',
'Hybrid/StorageInterface.php',
'Hybrid/User.php',
'Hybrid/User_Activity.php',
'Hybrid/User_Contact.php',
'Hybrid/User_Profile.php',
'Hybrid/thirdparty/OpenID/LightOpenID.php',
'Hybrid/thirdparty/LinkedIn/LinkedIn.php',
'Hybrid/thirdparty/LinkedIn/LinkedIn.php',
'Hybrid/thirdparty/OAuth/OAuth1Client.php',
'Hybrid/thirdparty/OAuth/OAuth2Client.php',
'Hybrid/thirdparty/OAuth/OAuth.php' );


foreach($ha3a as $ha3) {
require_once($ha3);
}

