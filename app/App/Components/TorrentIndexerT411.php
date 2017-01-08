<?php
namespace App\Components;

use Sunra\PhpSimple\HtmlDomParser;

//$fileSystem = $this->app['flysystems']['local__DIR__'];

class TorrentIndexerT411 extends TorrentIndexer{


	private function getT411Headers(){
		$cache = $this->app['caches']['filesystem'];
		$token = $cache->fetch('t411token');
		if(!$token){
			// $app['torrentCfg']['torrent.t411.authorization']
			$request = \Httpful\Request
			::post($this->app['torrentCfg']['torrent.t411.host'].'/auth')
			->body(http_build_query([
				'username' => $this->app['torrentCfg']['torrent.t411.username'],
				'password' => $this->app['torrentCfg']['torrent.t411.password']
			]))
			->addHeader('Content-Type','application/x-www-form-urlencoded')
			->withoutStrictSsl()
			->expectsJson()
			->sendsType(\Httpful\Mime::FORM);

			$reponse = $request->send();
			$cnt = $reponse->body;
			$token = $cnt->token;
			$cache->store('t411token',$token,60*60);
		}
		return array(
			'Authorization'	=> $token,
			'Accept'		=> 'Application/json',
			'Content-Type'	=> 'Application/json'
		);
	}

	public function getList($action,$type,$page,$query,$force){
		$limitByPage=30;
		$rootUrl = $this->app['torrentCfg']['torrent.t411.host'];
		$offset = 'offset='. (($page-1)*$limitByPage) . '&limit='.$limitByPage;
		switch($action){
			case 'search':
				switch($type){
					case 'series':
						$url = $rootUrl.'/torrents/search/'.$query.'?cid=433';
					break;
					case 'movies':
						$url = $rootUrl.'/torrents/search/'.$query.'?cid=631';
					break;
				}
			break;
			case 'list':
				switch($type){
					case 'series':
						$url = $rootUrl.'/torrents/search/?cid=433';
					break;
					case 'movies':
						$url = $rootUrl.'/torrents/search/?cid=631';
					break;
				}
			break;
		}
		$url =$url.'&'.$offset;

		$aHeaders = $this->getT411Headers();

		$cnt = $force?TorrentIndexer::getUrl($this->app,$url,TorrentIndexer::getCacheFilename($url),$aHeaders):TorrentIndexer::getCache($this->app,$url,10,$aHeaders);
		$cnt = preg_replace('!(<div (.*)\/div>)!','',$cnt);
		$aResult = json_decode(trim($cnt),true);

		$aTmp = [];
		$aTorrents=(empty($aResult['torrents'])?[]:$aResult['torrents']);
		foreach($aTorrents as $k=>&$torrent){
			$torrent = new SearchTorrentIndexerT411($this->app,$aHeaders,$type,$torrent);
			$torrent->run();
			$aTmp[] = $torrent->data;
		}
		return array(
			'engine'	=> 't411',
			'page'		=> $page,
			'totalItems'=> 10140,
			'items'		=> $aTmp
		);
	}

	public function getDetail($url){
		try{
			$fileSystem = $this->app['flysystems']['local__DIR__'];
			$cacheJsonFilename = TorrentIndexer::getCacheFilename($url,'json');
			if (!$fileSystem->has($cacheJsonFilename)){
				$aDetail = json_decode(TorrentIndexer::getCache($this->app,$url,3600*24*800,$aHeaders),true);
				if(is_array($aDetail)){
					$aP = nl2br(strip_tags($aDetail['description'],'<i><ul><li><p><span><div><br>'));
					while(strpos($aP,'<br><br>')!==false){
						$aP = str_replace('<br><br>','<br>',$aP);
					}
					$fileSystem->write($cacheJsonFilename,json_encode($aP));
				}
			}else{
				$aP = json_decode($fileSystem->read($cacheJsonFilename),true);
			}
		}catch(Exception $e){
			$aP=[$e->getMessage()];
		}
		$aP=preg_replace('!(_{3,})!','',$aP);
		return array (
			'desc' => html_entity_decode($aP)
		);
	}

	function getTorrent($app,$type,$url){
		$aHeaders = $this->getT411Headers();
		$torrent = TorrentIndexer::getUrl($app,$url,TorrentIndexer::getCacheFilename($url),$aHeaders);
		return $torrent;
	}
}

class SearchTorrentIndexerT411 //extends \Thread
{
	public function __construct($app,$aHeaders,$type,$torrent)
	{
		$this->app = $app;
		$this->type = $type;
		$this->aHeaders = $aHeaders;
		$this->torrent = $torrent;
	}

	public function run()
	{
		$rootUrl = $this->app['torrentCfg']['torrent.t411.host'];
		$fileSystem = $this->app['flysystems']['local__DIR__'];
		$tags=[];
		$sname=$this->torrent['name'];
		TorrentIndexer::extractTags($sname,$tags);
		$aDetail = json_decode(TorrentIndexer::getCache($this->app,$rootUrl.'/torrents/details/'.$this->torrent['id'],3600*24*800,$this->aHeaders),true);

		if(is_array($aDetail)){
			$oDetail = HtmlDomParser::str_get_html($aDetail['description'],true,true);
			if(is_object($oDetail)){
				$aP = array_map(function($v){
					return [
						'src'=>$v->src,
						'width'=>0+$v->width,
					];
				},$oDetail->find('img'));
				uasort($aP,function($a,$b){
					return $a['width']>$b['width'];
				});
			}
		}

		$versionHash = base64_encode($rootUrl.'/torrents/download/'.$this->torrent['id']);
		//db($tags);
		$aItem = array(
			'order'		=> $this->torrent['id'],
			//'otitle'	=> $this->torrent['name'],
			'title'		=> $sname,
			'date'		=> $this->torrent['added'],
			'href'		=> $this->torrent['rewritename'],
			'desc'		=> '',
			'img'		=> $aP[0]['src'],
			'mtype'		=> $this->type,
			'type'		=> $this->torrent['categoryname'],
			'subtype'	=> $this->torrent['categoryimage'],
			'tag'		=> $tags,
			'detail'	=> array(
				'state'	=> 'todo',
				'hash'	=> base64_encode($rootUrl.'/torrents/details/'.$this->torrent['id'])
			),
			'version'=> array(array(
				'hash'			=> $versionHash,
				'downloaded'	=> $fileSystem->has('/downloaded/t411/'.$versionHash)||$fileSystem->has('/marked/t411/'.$versionHash),
				'weight'		=> TorrentIndexer::human_filesize($this->torrent['size'],2),
				'up'			=> $this->torrent['seeders'],
				'down'			=> trim(html_entity_decode($this->torrent['leechers'])),
				'tag'			=> $tags,
				'french'		=> in_array('FRENCH',array_key_exists('language',$tags)?$tags['language']:array())
			))
		);
		$aItem['downloaded'] = !!($aItem['version'][0]['downloaded']);
		$aItem['french'] = !!($aItem['version'][0]['french']);
		$this->data= $aItem;
	}
}