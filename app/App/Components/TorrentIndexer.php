<?php
namespace App\Components;

use League\Flysystem\FilesystemInterface;

class TorrentIndexer{
	var $app;

	static $aAllTags=array(
			'quality'	=> array('BluRay 720p','BluRay 1080p','DVDSCR','DVDRIP','HDRIP','BRRIP','WEBRIP','x264','HDTV','PROPER','720p','WEB-DL','DD5 1','H264','1080p','H265','WEBRip','X264','XviD','DVDR','Multi','MPEG-2','AC3','AVC','mkv','AAC2','TVripHD','HEVC','x265','Avc','AAC','mp4','TVRip','H 264-BS','DD5 1-PSA','BluRay','x264-PopHD','MULTI','VFQ','DTS','J0D','DVD','MKV','LD'),
			'language'	=> array('VOSTFR','TRUEFRENCH','FRENCH','FASTSUB','SUBFRENCH','Vostfr','TVRip'),
			'year'		=> array()
	);
	function __construct($app){
		$this->app = $app;
	}

	public function getTorrent($app,$type,$url){
		return file_get_contents($url);
	}

	public function getList($action,$type,$page,$query,$force){}

	public function getDetail($url){}

	public static function getCacheFilename($url,$suffix=''){
		$aUrl = parse_url($url);
		return $aUrl['host'].'/'.substr(md5($url),0,16).'/'.substr(md5($url),17).($suffix==''?'':'.'.$suffix);
	}

	public static function getUrl($app,$url, $sLocalFile,$aheaders=array()){
		$fileSystem = $app['flysystems']['local__DIR__'];

		$request = \Httpful\Request::get($url)
		->addHeaders($aheaders);
		$cnt = $request->send()->body;
		//db($cnt);
		//db($request);
		$fileSystem->put($sLocalFile, $cnt);
		return $cnt;
	}

	public function FillCacheUrls($app,$aUrls){
		$fileSystem = $app['flysystems']['local__DIR__'];
		$aUrlToDos=array();
		$client = new Client();
		foreach($aUrls as $sUrl){
			$sLocalFile = self::getCacheFilename($sUrl);
			if(!$fileSystem->has($sLocalFile)){
				$requestArr[$sUrl] = $client->getAsync($sUrl);
			}
		}
		if(count($requestArr)){
			$time_start = microtime(true);
			$responses = \GuzzleHttp\Promise\unwrap($requestArr);
		}
		return true;
	}

	public static function getCache($app,$url,$cacheminutes,$aheaders=array()){
		/* @var $fileSystem FilesystemInterface */
		$fileSystem = $app['flysystems']['local__DIR__'];
		$todownload=false;
		$sLocalFile = self::getCacheFilename($url);
		if (!$fileSystem->has($sLocalFile)){ //if cache file doesn't exist
			$todownload=true;
		}else{
			if (((time()-$fileSystem->getTimestamp($sLocalFile))/60)>$cacheminutes && $cacheminutes!=-1) {
				$todownload=true;
			}
		}
		if($todownload){
			$cnt = self::getUrl($app,$url,$sLocalFile,$aheaders);
		}else{
			$cnt= $fileSystem->read($sLocalFile);
		}
		return $cnt;
	}

	public static function extractTags(&$sTitle,&$tags){
		if(!array_key_exists('year', self::$aAllTags)){
			for($i=1990;$i<=2050;$i++){
				self::$aAllTags['year'][]=$i;
			}
		}

		$sTitle	= ' '.preg_replace('!\.!',' ',$sTitle).' ';

		foreach(self::$aAllTags as $sTagType=>$aTag){
			foreach($aTag as $sTag){
				if (preg_match('! '.preg_quote($sTag,'!').' !',$sTitle)){
					$tags[$sTagType][]=$sTag;
					$sTitle = preg_replace('! '.preg_quote($sTag,'!').' !',' ',$sTitle);
				}
			}
		}

		if(preg_match('!(S([0-9]+)E([0-9]+))!',$sTitle,$saepMatch)){
			$tags['saep'] = array (
					'S' => $saepMatch [2],
					'E' => $saepMatch [3]
			);
			$sTitle = str_replace($saepMatch[1],' ',$sTitle);
		}

		$sTitle	= trim($sTitle);
		$sTitle	= preg_replace('!\s{2,}!',' ',$sTitle);
		return array($sTitle,$tags);
	}

	public function htmlTable($a){
		$s = '<table>';
		foreach($a as $k=>$row){
			if($k==0){
				$s .= '<tr>';
				foreach($row as $celName=>$cel){
					$s .= '<td><b>'.$celName.'</b></td>';
				}
				$s .= '</tr>';
			}
			$s .= '<tr>';
			foreach($row as $cel){
				$s .= '<td>'.$cel.'</td>';
			}
			$s .= '</tr>';
		}
		$s .= '<table>';
		return $s;
	}

	public static function human_filesize($bytes, $decimals = 2) {
		$size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
	}
}
