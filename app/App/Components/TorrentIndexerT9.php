<?php
namespace App\Components;
use Sunra\PhpSimple\HtmlDomParser;

class TorrentIndexerT9 extends TorrentIndexer{

	public function getList($action,$type,$page,$query,$force){
		$rootUrl = $this->app['torrentCfg']['torrent.t9.apihost'];
		$page = $page-1;
		$selector = '.table-responsive tr';
		switch($action){
			case 'list':
				switch($type){
					case 'series':
						$url = $rootUrl.'/torrents_series.html,page-'.$page;
					break;
					case 'movies':
						$url = $rootUrl.'/torrents_films.html,page-'.$page;
					break;
				}
			break;
			case 'search':
				switch($type){
					case 'series':
						$url = $rootUrl.'/search_torrent/series/'.str_replace(' ','-',html_entity_decode($query)).($page==0?'.html':'/page-'.($page));
					break;
					case 'movies':
						$url = $rootUrl.'/search_torrent/films/'.str_replace(' ','-',html_entity_decode($query)).($page==0?'.html':'/page-'.($page));
					break;
				}
				$selector = '.left-tab-section tr';
			break;
		}
		//print $this->getCache($this->app,$url,10);
		$dom = HtmlDomParser::str_get_html($force?TorrentIndexer::getUrl($this->app,$url,TorrentIndexer::getCacheFilename($url)):$this->getCache($this->app,$url,10), true,true);
		$aList = $dom->find($selector);
		$aResult = array();
		$order = 0;
		$iMaxPage = 0;
		foreach($dom->find('#pagination-mian a') as $oPage){
			$iMaxPage=max($iMaxPage,(int)$oPage->plaintext);
		}

		foreach($aList as $k=>$aItem){
			$href		= $aItem->find('a',0)->href;
			if($href==''){
				continue;
			}
			$withDate	= preg_match('!([0-9]{2})\/([0-9]{2})\/([0-9]{4})!',$aItem->find('a',0)->title,$dateMatch);
			$aUrl		= explode('/',$href);
			$sImg		= $this->app['torrentCfg']['torrent.t9.host'].'/_pictures/'.$aUrl[2].'.jpg';
			$sTitle		= $aItem->find('a',0)->plaintext;
			$tags		= array();
			TorrentIndexer::extractTags($sTitle,$tags);
			$aP		= array();
			$order++;
			$fileSystem = $this->app['flysystems']['local__DIR__'];

			$sTorrentUrl = $rootUrl.'/get_torrent/'.$aUrl[count($aUrl)-1].'.torrent';
			$k = $sTitle.'--'.(array_key_exists('saep',$tags)?(implode(',',$tags['language']).'--'.$tags['saep']['S'].'--'.$tags['saep']['E']):'');
			$hash = base64_encode($sTorrentUrl);
			$aVersion = array(
				'hash'			=> $hash,
				'downloaded'	=> $fileSystem->has('/downloaded/t9/'.$hash)||$fileSystem->has('/marked/t9/'.$hash),
				'weight'		=> trim(html_entity_decode($aItem->find('td'	,1)->plaintext)),
				'up'			=> trim(html_entity_decode($aItem->find('td'	,2)->plaintext)),
				'down'			=> trim(html_entity_decode($aItem->find('td'	,3)->plaintext)),
				'tag'			=> $tags,
				'french'		=> in_array('FRENCH',array_key_exists('language',$tags)?$tags['language']:array())
			);
			if(!array_key_exists($k,$aResult)){
				$aResult[$k]=array(
					'order'		=> $order,
					'title'		=> $sTitle,
					'date'		=> $withDate?$dateMatch[3].'-'.$dateMatch[2].'-'.$dateMatch[1]:'',
					'href'		=> str_replace($rootUrl,'/',$aItem->find('a',0)->href),
					'img'		=> $sImg,
					'detail'	=> array(
						'state'	=> 'todo',
						'hash'	=> base64_encode($href)
					),
					'mtype'		=> $type,
					'type'		=> '-',
					'subtype'	=> '-',
					'version'	=> array()
				);
			}
			$aResult[$k]['version'][]=$aVersion;

			if($aVersion['downloaded']){
				$aResult[$k]['downloaded']=true;
			}
			if($aVersion['french']){
				$aResult[$k]['french']=true;
			}
		}

		uasort($aResult,function($a,$b){
			return $a['order']-$b['order'];
		});

		return array (
			'engine' => 't9',
			'url' => $url,
			'type' => $type,
			'page' => $page,
			'totalItems' => ($iMaxPage + 1) * 30,
			'rootUrl' => $rootUrl,
			'items' => array_values ( $aResult )
		);
	}

	public function getDetail($url){
		$rootUrl = $this->app['torrentCfg']['torrent.t9.host'];
		try{
			$fileSystem = $this->app['flysystems']['local__DIR__'];
			$url= $rootUrl.$url;
			$cacheJsonFilename = TorrentIndexer::getCacheFilename($url,'json');
			if (!$fileSystem->has($cacheJsonFilename)){
				$oDetail = HtmlDomParser::str_get_html(TorrentIndexer::getUrl($this->app,$url,TorrentIndexer::getCacheFilename($url)),true,true);
				if(is_object($oDetail)){
					$aP = array_map(function($v){
						return html_entity_decode($v->plaintext);
					},array_slice($oDetail->find('.movie-detail .movie-information p'),2));
					$fileSystem->write($cacheJsonFilename,json_encode($aP));
				}
			}else{
				$aP = json_decode($fileSystem->read($cacheJsonFilename),true);
			}
		}catch(Exception $e){
			$aP=[$e->getMessage()];
		}
		return array (
			'desc' => html_entity_decode(implode("\n",$aP))
		);
	}

}
