<?php
namespace App\Components;

class TorrentIndexerT9 extends TorrentIndexer{
	public function svc_detail_cpasbien($app,$url){
		try{
			$fileSystem = $this->app['flysystems']['local__DIR__'];
			$cacheJsonFilename = TorrentIndexer::getCacheFilename($url,'json');
				if (!$fileSystem->has($cacheJsonFilename)){
				$oDetail = HtmlDomParser::str_get_html($this->getUrl($app,$url,TorrentIndexer::getCacheFilename($url)),true,true);
				if(is_object($oDetail)){
					$aP = array_map(function($v){
						return html_entity_decode($v->plaintext);
					},array_slice($oDetail->find('#textefiche p'),1));
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

	public function svc_cpasbien($app,$type,$page,$query,$force){
		$page = $page-1;
		$rootUrl='http://www.cpasbien.cm/';
		switch($type){
			case 'series':
				$url = $rootUrl.'view_cat.php?categorie=series&page='.$page;
			break;
			case 'movies':
				$url = $rootUrl.'view_cat.php?categorie=films&page='.$page;
			break;
			case 'search':
				$url = $rootUrl.'recherche/'.str_replace(' ','-',html_entity_decode($query)).($page==0?'.html':'/'+$page);
			break;
		}
		$dom = HtmlDomParser::str_get_html($force?$this->getUrl($app,$url,TorrentIndexer::getCacheFilename($url)):$this->getCache($url,10), true,true);
		$aList = $dom->find('.ligne0,.ligne1');
		$aResult = array();
		$order = 0;
		$iMaxPage = 0;
		foreach($dom->find('#pagination a') as $oPage){
			$iMaxPage=max($iMaxPage,(int)$oPage->plaintext);
		}

		foreach($aList as $k=>$aItem){
			//dump_html_tree($aItem,true,5);
			$href		= $aItem->find('a',0)->href;
			$withDate	= preg_match('!([0-9]{2})\/([0-9]{2})\/([0-9]{4})!',$aItem->find('a',0)->title,$dateMatch);
			$aUrl		= explode('/',$href);
			$sImg		= '_pictures/'.str_replace('.html','.jpg',$aUrl[6]);
			$sTitle		= $aItem->find('a',0)->plaintext;
			$sType		= $aUrl[4];
			$sSType		= $aUrl[5];
			$tags		= array();
			TorrentIndexer::extractTags($sTitle,$tags);
			$aP			= array();
			$order++;
			$fileSystem = $this->app['flysystems']['local__DIR__'];

			$sTorrentUrl = $rootUrl.'telechargement/'.str_replace('.html','.torrent',$aUrl[count($aUrl)-1]);
			$k = $sTitle.'--'.(array_key_exists('saep',$tags)?(implode(',',$tags['language']).'--'.$tags['saep']['S'].'--'.$tags['saep']['E']):'');
			$hash = base64_encode($sTorrentUrl);
			$aVersion = array(
				'hash'			=> $hash,
				'downloaded'	=> $fileSystem->has('/downloaded/cpasbien/'.$hash)||$fileSystem->has('/marked/cpasbien/'.$hash),
				'weight'		=> trim(html_entity_decode($aItem->find('.poid'	,0)->plaintext)),
				'up'			=> trim(html_entity_decode($aItem->find('.up'	,0)->plaintext)),
				'down'			=> trim(html_entity_decode($aItem->find('.down'	,0)->plaintext)),
				'tag'			=> $tags,
				'french'		=> in_array('FRENCH',array_key_exists('language',$tags)?$tags['language']:array())
			);
			if(!array_key_exists($k,$aResult)){
				$aResult[$k]=array(
					'order'		=> $order,
					'title'		=> $sTitle,
					'date'		=> $withDate?$dateMatch[3].'-'.$dateMatch[2].'-'.$dateMatch[1]:'',
					'href'		=> str_replace($rootUrl,'',$aItem->find('a',0)->href),
					'img'		=> $sImg,
					'detail'	=> array(
						'state'	=> 'todo',
						'hash'	=> base64_encode($href)
					),
					'type'		=> $sType,
					'subtype'	=> $sSType,
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
		return array(
			'engine'	=> 'cpasbien',
			'url'		=> $url,
			'type'		=> $type,
			'page'		=> $page,
			'totalItems'=> ($iMaxPage+1)*30,
			'rootUrl'	=> $rootUrl,
			'items'		=> array_values($aResult)
		);
	}
}