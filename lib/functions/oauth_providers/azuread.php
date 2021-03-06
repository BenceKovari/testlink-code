<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * @filesource  google.php
 *
 * Google OAUTH API (authentication)
 *
 * @internal revisions
 * @since 1.9.20
 *
 */

//Get token
function oauth_get_token($authCfg, $code) {

  $result = new stdClass();
  $result->status = array('status' => tl::OK, 'msg' => null);

  //Params to get token
  $oauthParams = array(
    'code'          => $code,
    'grant_type'    => $authCfg['oauth_grant_type'],
    'client_id'     => $authCfg['oauth_client_id'],
    'client_secret' => $authCfg['oauth_client_secret']
  );

  $oauthParams['redirect_uri'] = trim($authCfg['redirect_uri']);     
  if( isset($_SERVER['HTTPS']) ) {
    $oauthParams['redirect_uri'] = 
      str_replace('http://', 'https://', $oauthParams['redirect_uri']);  
  }  

  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $authCfg['token_url']);
  curl_setopt($curl, CURLOPT_POST, 1);
  curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($oauthParams));
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
  $result_curl = curl_exec($curl);
  curl_close($curl);
  $tokenInfo = json_decode($result_curl, true);

  // At this point we may turn to the user_info endpoint for additional information
  // but for now, we are going to ignore it, as all neccessary information is available
  // in the id_token
  if (isset($tokenInfo['id_token'])){
    list($header, $payload, $signature) = explode(".", $tokenInfo['id_token']);
    $userInfo = json_decode(base64_decode ($payload), true);

    if (isset($userInfo['oid'])){
      if (isset($authCfg['oauth_domain'])) {
        $domain = substr(strrchr($userInfo['upn'], "@"), 1);
        if ($domain !== $authCfg['oauth_domain']){
          $result->status['msg'] = 
          "TestLink Oauth policy - User email domain:$domain does not 
           match \$authCfg['oauth_domain']:{$authCfg['oauth_domain']} ";
          $result->status['status'] = tl::ERROR;
        }
      }
    } else {
      $result->status['msg'] = 'TestLink - User ID is empty';
      $result->status['status'] = tl::ERROR;
    }

    $options = new stdClass();
    $options->givenName = $userInfo['given_name'];
    $options->familyName = $userInfo['family_name'];
    $options->user = $userInfo['upn'];
    $options->auth = 'oauth';

    $result->options = $options;
  } else {
    $result->status['msg'] = 'TestLink - An error occurred during get token e'.$result_curl.'e';
    $result->status['status'] = tl::ERROR;
  }

  return $result;

}
