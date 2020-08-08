<?php
$apiUrl = 'https://api.github.com/orgs/BoomTownROI';
$helper = new BoomTownApiTestHelper();
$response = $helper->getRequest($apiUrl);

//Part 1: Output Data
foreach ($response as $key => $index) {
    if (strpos(strtolower($index), strtolower($apiUrl)) !== FALSE && strtolower($index) !== strtolower($apiUrl)) {
        //Remove the optional url param from string
        if (strpos($index, '{') !== FALSE) {
            $index = strstr($index, '{', TRUE);
        }
        $helper->printRequestKey($index);
    }
}

//Part 2: Verification
printf("Checking if created_date is before updated_date: " . $helper->checkUpdateDate($response->created_at, $response->updated_at) . "\n\n");
printf("Comparing public_repos count: " . $response->public_repos . " vs manual count: " . $helper->countAllResults($response->repos_url) . "\n\n");

/**
 * Would normally put class in its own php file and call it in to this file
 *
 * Class BoomTownApiTestHelper
 */
class BoomTownApiTestHelper
{
    private $_datetime;

    /**
     * BoomTownApiTestHelper constructor.
     */
    function __construct()
    {
        $this->_datetime = new DateTime();
    }

    /**
     * Curls given url with options returns JSON decoded request object with additional status code field
     *
     * @param $url
     * @param array $options
     * @return array|mixed
     */
    public static function getRequest($url, $options = [])
    {
        //Default Curl Options
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Andrew Code Test',
            CURLOPT_RETURNTRANSFER => TRUE
        ];

        //Add/Replace options from parameters
        if (!empty($options)) {
            $curlOptions = array_replace($curlOptions, $options);
        }

        //Curl it up
        $curl = curl_init();
        curl_setopt_array($curl, $curlOptions);
        $response = curl_exec($curl);
        $resCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        //Decode it like its hot
        $response = json_decode($response);

        //Add status code attribute
        if (is_object($response)) {
            $response->response_code = $resCode;
        } elseif (is_array($response)) {
            $response['response_code'] = $resCode;
        }

        return $response;
    }

    /**
     * Prints the IDs of child objects retrieved from given url
     *
     * @param $url
     * @param array $options
     */
    public function printRequestKey($url, $options = [])
    {
        $response = $this->getRequest($url, $options);

        if ($response->response_code >= 400) { //Check status code and output as needed
            printf("Request to " . $url . " has returned a Status Code: " . $response->response_code . " " . $response->message . ".");
            printf(" Please refer to " . $response->documentation_url . " for more help. \n");
        } else {
            printf("IDs of items from " . $url . ":\n");
            foreach ($response as $resKey => $resObj) {
                if ($resKey !== "response_code") {
                    printf("ID | " . $resObj->id . "\n");
                }
            }
        }

        printf("\n");
    }

    /**
     * Checks if the updated date is later then the created date
     *
     * @param $created
     * @param $updated
     * @return string
     */
    public function checkUpdateDate($created, $updated)
    {
        $createdDate = $this->_datetime->createFromFormat('Y-m-d\TH:i:s\Z', $created);
        $updatedDate = $this->_datetime->createFromFormat('Y-m-d\TH:i:s\Z', $updated);

        if ($createdDate < $updatedDate) {
            return "Yes";
        } else {
            return "No";
        }
    }

    /**
     * Counts all items of endpoint
     *
     * @param $url
     * @param int $pageNumber
     * @param int $pageSize
     * @param array $options
     * @return int
     */
    public function countAllResults($url, $pageNumber = 1, $pageSize = 30, $options = [])
    {
        $pageinationUrl = $url . "?page=$pageNumber&per_page=$pageSize";
        $response = $this->getRequest($pageinationUrl, $options);
        $count = 0;

        foreach ($response as $key => $object) {
            if ($key !== 'response_code') { //ignore out custom attribute
                $count++;
            }
        }

        if ($count == $pageSize) { //if count is equal to the page size then endpoint could have more items
            $pageNumber++;
            $count += $this->countAllResults($url, $pageNumber, $pageSize);
        }

        return $count;
    }
}
