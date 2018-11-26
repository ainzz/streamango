<?php

/*
 * This file is part of the ideneal/openload library
 *
 * (c) Daniele Pedone <ideneal.ztl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BurakBoz\streamango;

use GuzzleHttp\Client;
use BurakBoz\streamango\Builder\AccountInfoBuilder;
use BurakBoz\streamango\Builder\ContentBuilder;
use BurakBoz\streamango\Builder\ConversionStatusBuilder;
use BurakBoz\streamango\Builder\FileInfoBuilder;
use BurakBoz\streamango\Builder\LinkBuilder;
use BurakBoz\streamango\Builder\RemoteUploadBuilder;
use BurakBoz\streamango\Builder\TicketBuilder;
use BurakBoz\streamango\Entity\AbstractContent;
use BurakBoz\streamango\Entity\AccountInfo;
use BurakBoz\streamango\Entity\ConversionStatus;
use BurakBoz\streamango\Entity\DownloadLink;
use BurakBoz\streamango\Entity\File;
use BurakBoz\streamango\Entity\FileInfo;
use BurakBoz\streamango\Entity\Folder;
use BurakBoz\streamango\Entity\RemoteUpload;
use BurakBoz\streamango\Entity\RemoteUploadStatus;
use BurakBoz\streamango\Entity\Ticket;
use BurakBoz\streamango\Entity\UploadLink;
use BurakBoz\streamango\Exception\BadRequestException;
use BurakBoz\streamango\Exception\BandwidthUsageExceededException;
use BurakBoz\streamango\Exception\FileNotFoundException;
use BurakBoz\streamango\Exception\PermissionDeniedException;
use BurakBoz\streamango\Exception\ServerException;
use BurakBoz\streamango\Exception\UnavailableForLegalReasonsException;
use Psr\Http\Message\ResponseInterface;

/**
 * streamangoClient
 *
 * @author Daniele Pedone aka Ideneal <ideneal.ztl@gmail.com>
 */
class streamangoClient
{
    const API_BASE_URL = 'https://api.fruithosted.net';
    const API_VERSION  = 1;

    /**
     * @var string The API login string
     */
    private $login;

    /**
     * @var string The API key string
     */
    private $key;

    /**
     * @var Client The http client
     */
    private $client;

    /**
     * The OpenLoad service client constructor
     *
     * @param string $login The API login string
     * @param string $key   The API key string
     */
    public function __construct($login, $key)
    {
        $this->login = $login;
        $this->key   = $key;

        $baseUri = self::API_BASE_URL . '/';

        $this->client = new Client(array(
            'base_uri' => $baseUri
        ));
    }

    /**
     * Returns the account info
     *
     * @return AccountInfo
     */
    public function getAccountInfo()
    {
        $params   = $this->getAuthParams();
        $response = $this->processRequest('/account/info', $params);
        $result   = $this->processResponse($response);
        return AccountInfoBuilder::buildAccountInfo($result);
    }

    /**
     * Returns the ticket to download a file
     *
     * @param string|FileInfo $file The file id
     *
     * @return Ticket
     */
    public function getTicket($file)
    {
        $params   = ['file' => (string) $file];
        $response = $this->processRequest('/file/dlticket', $params);
        $result   = $this->processResponse($response);
        $ticket   = TicketBuilder::buildTicket($result);

        $ticket->setFileId((string) $file);

        return $ticket;
    }

    /**
     * Returns the download link
     *
     * @param Ticket $ticket          The ticket previously generated
     * @param string $captchaResponse The captcha response
     *
     * @return DownloadLink
     */
    public function getDownloadLink(Ticket $ticket, $captchaResponse = null)
    {
        $params = [
            'file' => $ticket->getFileId(),
            'ticket' => $ticket->getCode()
        ];

        if ($captchaResponse) {
            $params['captcha_response'] = $captchaResponse;
        }

        $response = $this->processRequest('/file/dl', $params);
        $result   = $this->processResponse($response);

        return LinkBuilder::buildDownloadLink($result);
    }


    /**
     * Returns the files info
     *
     * @param array $files The files id
     *
     * @return FileInfo[]
     */
    public function getFilesInfo(array $files)
    {
        $params = $this->getAuthParams();

        $params['file'] = implode(',', $files);

        $response = $this->processRequest('/file/info', $params);
        $results  = $this->processResponse($response);

        $filesInfo = [];
        foreach ($results as $result) {
            $filesInfo[] = FileInfoBuilder::buildFileInfo($result);
        }

        return $filesInfo;
    }

    /**
     * Returns the file info
     *
     * @param string $file The file id
     *
     * @return FileInfo
     */
    public function getFileInfo($file)
    {
        return current($this->getFilesInfo([$file]));
    }

    /**
     * Returns the upload link
     *
     * @param string|Folder $folder   The folder id
     * @param string        $sha1     The sha1 of file to upload
     * @param bool          $httpOnly If this is set to true, use only http upload links
     *
     * @return UploadLink
     */
    public function getUploadLink($folder = null, $sha1 = null, $httpOnly = false)
    {
        $params = $this->getAuthParams();

        if ($folder) {
            $params['folder'] = (string) $folder;
        }

        if ($sha1) {
            $params['sha1'] = (string) $sha1;
        }

        if ($httpOnly) {
            $params['httponly'] = true;
        }

        $response = $this->processRequest('/file/ul', $params);
        $result   = $this->processResponse($response);

        return LinkBuilder::buildUploadLink($result);
    }

    /**
     * Uploads a remote file
     *
     * @param string        $url     The remote file url
     * @param string|Folder $folder  The folder id
     * @param array         $headers The request headers
     *
     * @return RemoteUpload
     */
    public function uploadRemoteFile($url, $folder = null, array $headers = array())
    {
        $params = $this->getAuthParams();

        $params['url'] = $url;

        if ($folder) {
            $params['folder'] = (string) $folder;
        }

        foreach ($headers as $name => $header) {
            $params['headers'] .= $name.": ".$header."\n";
        }

        $response = $this->processRequest('/remotedl/add', $params);
        $result   = $this->processResponse($response);

        return RemoteUploadBuilder::buildRemoteUpload($result);
    }

    /**
     * Returns the status of the remote upload
     *
     * @param RemoteUpload $remoteUpload The remote upload
     *
     * @return RemoteUploadStatus
     */
    public function getRemoteUploadStatus(RemoteUpload $remoteUpload)
    {
        $params = $this->getAuthParams();
        $params['id'] = $remoteUpload->getId();

        $response = $this->processRequest('/remotedl/status', $params);
        $result   = $this->processResponse($response);

        // TODO fix this shit
        if (is_array($result) && count($result) == 1) {
            $result = current($result);
        }

        return RemoteUploadBuilder::buildRemoteUploadStatus($result);
    }

    /**
     * Returns the latest remote upload statuses
     *
     * @param int $limit The maximum number of result (maximum 100)
     *
     * @return RemoteUploadStatus[]
     */
    public function getLatestRemoteUploadStatuses($limit = 5)
    {
        $params = $this->getAuthParams();
        $params['limit'] = max([0, min([$limit, 100])]);

        $response = $this->processRequest('/remotedl/status', $params);
        $results  = $this->processResponse($response);

        $status = [];
        foreach ($results as $result) {
            $status[] = RemoteUploadBuilder::buildRemoteUploadStatus($result);
        }

        return $status;
    }

    /**
     * Returns all contents (folder and file) within a folder
     *
     * @param string|Folder $folder The folder id
     *
     * @return AbstractContent[]
     */
    public function getContents($folder = null)
    {
        $params = $this->getAuthParams();

        if ($folder) {
            $params['folder'] = (string) $folder;
        }

        $response = $this->processRequest('/file/listfolder', $params);
        $results  = $this->processResponse($response);

        $contents = [];

        foreach ($results['folders'] as $result) {
            $contents[] = ContentBuilder::buildFolder($result);
        }

        foreach ($results['files'] as $result) {
            $contents[] = ContentBuilder::buildFile($result);
        }

        return $contents;
    }

    /**
     * Returns the folders within a folder
     *
     * @param string|Folder $folder The folder id
     *
     * @return Folder[]
     */
    public function getFolders($folder = null)
    {
        $params = $this->getAuthParams();

        if ($folder) {
            $params['folder'] = (string) $folder;
        }

        $response = $this->processRequest('/file/listfolder', $params);
        $results  = $this->processResponse($response);

        $contents = [];

        foreach ($results['folders'] as $result) {
            $contents[] = ContentBuilder::buildFolder($result);
        }

        return $contents;
    }

    /**
     * Returns the files within a folder
     *
     * @param string|Folder $folder The folder id
     *
     * @return File[]
     */
    public function getFiles($folder = null)
    {
        $params = $this->getAuthParams();

        if ($folder) {
            $params['folder'] = (string) $folder;
        }

        $response = $this->processRequest('/file/listfolder', $params);
        $results  = $this->processResponse($response);

        $contents = [];

        foreach ($results['files'] as $result) {
            $contents[] = ContentBuilder::buildFile($result);
        }

        return $contents;
    }

    /**
     * Converts a file
     *
     * @param string|File $file The file id
     *
     * @return boolean
     */
    public function convertFile($file)
    {
        $params = $this->getAuthParams();
        $params['file'] = (string) $file;

        $response = $this->processRequest('/file/convert', $params);
        $result   = $this->processResponse($response);

        return $result;
    }

    /**
     * Returns the running conversions
     *
     * @param string|Folder $folder The folder id
     *
     * @return ConversionStatus[]
     */
    public function getRunningConversions($folder = null)
    {
        $params = $this->getAuthParams();

        if ($folder) {
            $params['folder'] = (string) $folder;
        }

        $response = $this->processRequest('/file/runningconverts', $params);
        $results  = $this->processResponse($response);

        $conversions = [];

        foreach ($results as $result) {
            $conversions[] = ConversionStatusBuilder::buildConversionStatus($result);
        }

        return $conversions;
    }

    /**
     * Returns the url of video splash image
     *
     * @param string|File $file The file id
     *
     * @return string
     */
    public function getVideoSplashImage($file)
    {
        $params = $this->getAuthParams();
        $params['file'] = (string) $file;

        $response = $this->processRequest('/file/getsplash', $params);
        $result   = $this->processResponse($response);

        return $result;
    }

    /**
     * Uploads a file
     *
     * @param string        $fileName The file name of the file to upload
     * @param string|Folder $folder   The folder id where upload the file
     * @param bool          $httpOnly If this is set to true, use only http upload links
     *
     * @return mixed
     */
    public function uploadFile($fileName, $folder = null, $httpOnly = false)
    {
        $file = fopen($fileName, 'r');
        $sha1 = sha1_file($fileName);

        $uploadLink = $this->getUploadLink($folder, $sha1, $httpOnly);

        $response = $this->client->request('POST', $uploadLink->getUrl(), [
            'multipart' => [
                [
                    'name'     => basename($fileName),
                    'contents' => $file
                ]
            ]
        ]);

        return $this->processResponse($response);
    }

    /**
     * Processes the OpenLoad API request
     *
     * @param string $uri        The request uri
     * @param array  $parameters The parameters array
     *
     * @return ResponseInterface
     */
    protected function processRequest($uri, array $parameters)
    {
        return $this->client->get($uri, array(
            'query' => $parameters
        ));
    }

    /**
     * Processes the OpenLoad API response and returns the result
     *
     * @param ResponseInterface $response The OpenLoad API response
     *
     * @return mixed
     *
     * @throws BadRequestException
     * @throws PermissionDeniedException
     * @throws FileNotFoundException
     * @throws UnavailableForLegalReasonsException
     * @throws BandwidthUsageExceededException
     * @throws ServerException
     */
    protected function processResponse(ResponseInterface $response)
    {
        $json = $response->getBody();
        $data = json_decode($json, true);

        $msg = $data['msg'];

        if ($data['status'] >= 300) {
            switch ($data['status']) {
                case 400:
                    throw new BadRequestException($msg);
                case 403:
                    throw new PermissionDeniedException($msg);
                case 404:
                    throw new FileNotFoundException($msg);
                case 451:
                    throw new UnavailableForLegalReasonsException($msg);
                case 509:
                    throw new BandwidthUsageExceededException($msg);
                default :
                    throw new ServerException($msg);
            }
        }

        return $data['result'];
    }

    /**
     * Returns the authentication parameters
     *
     * @return array
     */
    protected function getAuthParams()
    {
        return array(
            'login' => $this->login,
            'key'   => $this->key
        );
    }
}
