<?php

//
// Open Web Analytics - An Open Source Web Analytics Framework
//
// Copyright 2006 Peter Adams. All rights reserved.
//
// Licensed under GPL v2.0 http://www.gnu.org/copyleft/gpl.html
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//
// $Id$
//

if(!class_exists('owa_observer')) {
	require_once(OWA_BASE_DIR.'owa_observer.php');
}	

require_once(OWA_BASE_DIR.DIRECTORY_SEPARATOR.'ini_db.php');

if (!class_exists('owa_http')) {
	require_once(OWA_BASE_DIR.DIRECTORY_SEPARATOR.'owa_httpRequest.php');
}

/**
 * OWA Referer Event handlers
 * 
 * @author      Peter Adams <peter@openwebanalytics.com>
 * @copyright   Copyright &copy; 2006 Peter Adams <peter@openwebanalytics.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GPL v2.0
 * @category    owa
 * @package     owa
 * @version		$Revision$	      
 * @since		owa 1.0.0
 */

class owa_refererHandlers extends owa_observer {
    
	/**
	 * Constructor
	 *
	 * @param 	string $priority
	 * @param 	array $conf
	 * 
	 */
    function owa_refererHandlers() {
        
    	// Call the base class constructor.
        $this->owa_observer();
		return;
    }
	
    /**
     * Notify Event Handler
     *
     * @param 	unknown_type $event
     * @access 	public
     */
    function notify($event) {
		
		if (!$event->get('external_referer')) {
			return;
		}
		
    	// Make entity
		$r = owa_coreAPI::entityFactory('base.referer');
		
		// set referer url
		$r->set('url', $event->get('HTTP_REFERER'));
		
		// check for search engine
		$se_info = $this->lookupSearchEngine($event->get('HTTP_REFERER'));
		if (!empty($se_info)):
			$r->set('is_searchengine', true);
			$r->set('site_name', $se_info->name);
		endif;
		
		// Set site
		$url = parse_url($event->get('HTTP_REFERER'));
		$r->set('site', $url['host']);
		
		if ($event->get('query_terms')) {
			$r->set('query_terms', $event->get('query_terms'));
		}
				
		if ($event->get('source') === 'organic-search') {
			$r->set('is_searchengine', true);
		}
			
		// set title. this will be updated later by the crawler.
		$r->set('page_title', $event->get('HTTP_REFERER'));
		// Set id
		$r->set('id', owa_lib::setStringGuid($event->get('HTTP_REFERER')));
		// Persist to database
		$r->create();
		
		// Crawl and analyze refering page
		if (owa_coreAPI::getSetting('base', 'fetch_refering_page_info')) {
			//owa_coreAPI::debug('hello from logReferer');
			$crawler = new owa_http;
			//$crawler->fetch($this->params['HTTP_REFERER']);
			$res = $crawler->getRequest($event->get('HTTP_REFERER'), $response);
			owa_coreAPI::debug(print_r($res, true));
			//Extract Title
			
			$title = trim($crawler->extract_title());
			
			if ($title) {
				$r->set('page_title', $title);	
			}			
			
			$se = $r->get('is_searchengine');
			//Extract anchortext and page snippet but not if it's a search engine...
			if ($se != true) {
				$r->set('snippet', $crawler->extract_anchor_snippet($event->get('inbound_page_url')));
				//$this->e->debug('Referering Snippet is: '. $this->snippet);
				$r->set('refering_anchortext', $crawler->anchor_info['anchor_text']);
				//$this->e->debug('Anchor text is: '. $this->anchor_text);
			}
				
			//write to DB
			$r->update();
			
		}
    }
    
    /**
	 * Lookup info about referring domain 
	 *
	 * @param string $referer
	 * @return object
	 * @access private
	 */
	function lookupSearchEngine($referer) {
	
		/*	Look for match against Search engine groups */
		$db = new ini_db(owa_coreAPI::getSetting('base', 'search_engines.ini'), $sections = true);
		
		$se_info = $db->fetch($referer);
		
		if (!empty($se_info->name)):
			return $se_info;
		else:
			return false;
		endif;
			
	}
    
}

?>