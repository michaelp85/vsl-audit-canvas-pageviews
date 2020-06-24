<?php
/**
 * Created by Michael Pasqualone <michaelp@aie.edu.au>
 *  to obtain all page_view data via Canvas API for VSL Audit purposes
 *  exporting to individual CSV files for review.
 *
 * Code is sloppy, but gets the job done!
 */

// Setup
$token = 'Redacted)'; // Canvas API token
$canvasUrl = 'aie.instructure.com';

// Third-party libraries
use League\Csv\Reader;
use League\Csv\Writer;

require './csv-9.6.0/autoload.php';

$reader = Reader::createFromPath('./userids.csv', 'r');
$reader->setHeaderOffset(0);
$records = $reader->getRecords();
foreach ($records as $offset => $record) {
	/**
	 * Qual = Qualification
	 * StudentId = Active Directory username
	 * Canvas_User_id = Canvas internal user id
	 */

	 $userId = $record['Canvas_User_id'];
	 $studentId = $record['StudentId'];

	 echo "-- Processing: $userId - $studentId --\n";

	 $url = "https://$canvasUrl/api/v1/users/$userId/page_views?per_page=100"; // 100 per page is the maximum we can return via this API endpoint
	 $curl = curl_init();
	 curl_setopt($curl, CURLOPT_VERBOSE, 0);
	 curl_setopt($curl, CURLOPT_URL, $url);
	 curl_setopt($curl, CURLOPT_HTTPHEADER, array(
	 	'Authorization: Bearer ' . $token,
	 	'Content-Type: application/json'
	 ));

	 //curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	 curl_setopt($curl, CURLOPT_HEADER, 1);
	 curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	 curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	 curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

	 // EXECUTE:
	 $result = curl_exec($curl);

	 $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
	 $header = substr( $result, 0, $header_size );
	 $body = substr( $result, $header_size );

	 //echo $header;

	 $count = 1;

	 while ( preg_match( '/^link: (.*)$/im', $header, $matches ) ) {
	 	echo "-- Count: $count --\n";

	 	//echo "- Header:\n";
	 	//echo $header;

	 	$link_info = $matches[1];
	 	$links = explode( ',', $link_info );
	 	$nextpage = NULL;
	 	foreach ( $links as $link ) {
	 		if (preg_match( '/<(.*?)>; rel="next"$/', $link, $matches )) {
	 			$nextpage = $matches[1];
	 			echo "- Next Page: $nextpage\n";
	 		}
	 	}

	 	if (!isset( $nextpage )) {
	 		break;
	 	}

	 	curl_setopt( $curl, CURLOPT_URL, $nextpage );

	 	$nextResult = curl_exec($curl);

	 	if ($nextResult !== FALSE) {
	 		echo "- Getting next page\n";
	 		$header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
	 		$header = substr( $nextResult, 0, $header_size );

	 		//echo "- Next Header:\n";
	 		//echo $header;
	 		$body .= substr( $nextResult, $header_size );
	 		//phpecho $header;
	 	}

	 	$count++;

	 	// Only loop 2 times for debugging purposes
	 	/* if($count == 2) {
	 		break;
	 	} */
	 }

	 if (preg_match( '/\]\[/', $body )) {
	 	$body = preg_replace( '/\]\[/', ',', $body );
	 }

	 $json = json_decode($body);

	 $writer = Writer::createFromPath("./export/$studentId.csv", 'w+');
	 $writer->insertOne(['user_id', 'created_at', 'url', 'user_agent', 'http_method', 'remote_ip', 'id', 'session_id']); // Create the header

	 // Loop over results
	 foreach ($json as $page_view) {
	 	$writer->insertOne([
			$page_view->links->user,
			$page_view->created_at,
			$page_view->url,
			$page_view->user_agent,
			$page_view->http_method,
			$page_view->remote_ip,
			$page_view->id,
			$page_view->session_id,
		]);
	 }

	 if(!$result) {
	 	die("Connection Failure");
	 }
}


curl_close($curl);
?>
