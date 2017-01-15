<?php
namespace App\Controllers;

use \Vio\PHPTorrents\ClientConnection;
use \Vio\PHPTorrents\Torrent;
use App\Components\TorrentIndexer;
use DDesrosiers\SilexAnnotations\Annotations as SLX;
use GuzzleHttp\Client;
use Silex\Application;
use SM\SilexRestApi\Controllers\NormalizedResponse;
use Symfony\Component\HttpFoundation\Request;
use Vio\PHPTorrents\Client\Deluge\ClientAdapter as DelugeClient;

class Torrents {
	use NormalizedResponse;

	function __construct(){
	}

	/**
	 *
	 * @return TorrentIndexer
	 */
	private Function getProvider($app,$provider){
		$className = 'App\Components\TorrentIndexer'.ucfirst($provider);
		return new $className($app);
	}

	/**
	 * @SLX\Route(
	 *     @SLX\Request(uri="dwn/{dst}/{provider}/{type}/{hash}"),
	 * )
	 */
	public function dwn(Application $app,Request $request,$dst,$provider,$type,$hash){
		$this->app = $app;
		$this->mark($app, $request, $provider, $hash,'downloaded');
		$url = base64_decode($hash);
		$torrent = $this->getProvider($app,$provider)->getTorrent($app,$type,$url);

		switch($dst){
			case 'local':
				$sOutPath = $app['torrentCfg']['torrent.local.path'].$type;
				if(!file_exists($sOutPath)){
					mkdir ($sOutPath);
				}
				$res = file_put_contents($sOutPath.'/'.$hash.'.torrent',$torrent);
				//db($sOutPath.'/'.$hash.'.torrent');
				//$res = file_put_contents($sOutPath.'/uu',"eezez");
				//db('------');
				//db(glob($sOutPath.'/*'));
			break;
			case 'seed':
				$conn_id = ftp_connect($app['torrentCfg']['torrent.seed.host']);
				$login_result = ftp_login($conn_id, $app['torrentCfg']['torrent.seed.user'], $app['torrentCfg']['torrent.seed.password']);
				$tmpFileName='/tmp/'.$hash.'.torrent';;
				file_put_contents($tmpFileName,$torrent);
				if (ftp_put($conn_id, $app['torrentCfg']['torrent.seed.path'].$type.'/'.$hash.'.torrent', $tmpFileName, FTP_BINARY)) {
					$res= "Le fichier $file a été chargé avec succès\n";
				} else {
					$res= "Il y a eu un problème lors du chargement du fichier $file\n";
				}
				unlink($tmpFileName);
			break;
		}

		return $this->formatResponse($request, $app, [$url,$res]);
	}

	/**
	 * @SLX\Route(
	 *     @SLX\Request(uri="mark/{provider}/{hash}"),
	 * )
	 */
	public function mark(Application $app,Request $request,$provider,$hash,$type='marked'){
		$this->app = $app;
		$fileSystem = $this->app['flysystems']['local__DIR__'];
		$url = base64_decode($hash);
		$torrent = file_get_contents($url);
		if(!$fileSystem->has('/'.$type.'/'.$provider.'/'.$hash)){
			$fileSystem->write('/'.$type.'/'.$provider.'/'.$hash,$torrent);
		}

		return $this->formatResponse($request, $app, $url);
	}

	/**
	 * @SLX\Route(
	 *     @SLX\Request(uri="detail/{provider}/{hash}"),
	 * )
	 */
	public function detail(Application $app,Request $request,$provider,$hash){
		return $this->formatResponse($request, $app, $this->getProvider($app,$provider)->getDetail(base64_decode($hash)));
	}

	/**
	 * @SLX\Route(
	 *     @SLX\Request(uri="{provider}/{action}/{type}"),
	 * )
	 */
	public function search(Application $app,Request $request,$provider,$action,$type){
		$this->app = $app;
		$page = $request->get('page','');
		return $this->formatResponse($request, $app, $this->getProvider($app,$provider)->getList($action,$type,$page,$request->get('query',''),$request->get('force',0)));
	}

	/**
	 * @SLX\Route(
	 *     @SLX\Request(uri="downloads"),
	 * )
	 */
	public function downloads(Application $app,Request $request){
		$this->app = $app;

		$client = new DelugeClient((new ClientConnection())
			->setHost		($this->app['torrentCfg']['torrent.deluge.host'])
			->setProtocol	($this->app['torrentCfg']['torrent.deluge.protocol'])
			->setPort		($this->app['torrentCfg']['torrent.deluge.port'])
			->setPassword	($this->app['torrentCfg']['torrent.deluge.password'])
		);

		$torrentList = $client->getTorrents();
		$aList = [];
		foreach($torrentList as $torrent){
			/** @var $torrent Torrent */
			$aList[]=[
					'name'	=> $torrent->getName(),
					'hash'	=> $torrent->getHashString(),
					'done'	=> $torrent->getBytesDownloaded(),
					'size'	=> $torrent->getSize(),
					'status'=> $torrent->getStatus(),
					'speed'	=> $torrent->getDownloadSpeed(),
					'eta'	=> date('H:i:s',$torrent->getETA()/1000)
			];
		}

		return $this->formatResponse($request, $app, $aList);
	}
}
