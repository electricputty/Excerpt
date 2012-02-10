<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 * File:		pi.zm_excerpt.php
 * Authors:		Zoom Studios / Dan Walton (updated to EE2 by Rob Hodges @electriclabs @electricputty )
 * Ver:			2.0
 * Date:		10/02/2012 
 * Purpose:		Expression Engine plugin used to highlight search terms in context sensitive
 * 				search results.
 * Changelog:
 * 				1.0.0 -> 1.0.1 (03/09/2007)
 * 					- Added more parameters
 * 					  (chunk_append/chunk_prepend/output_wrap/output_append/output_prepend)
 * 				1.0.1 -> 1.0.2 (05/09/2007)
 * 					- Added 'keywords' parameter, unrestricting this plugins usage from just the
 * 					  search results template.
 * 					- Added sort parameter. (desc/asc) default is 'asc'.
 * 					- Added order parameter, currently only accepts 'random'
 * 					- Removed possible php error with no chunks found.
 * 					- Added ability to not wrap chunks with span by using "none" in the chunk_wrap
 * 					  parameter.
 * 					- Added ranges to pre and post chars.
 * 				1.0.2 -> 1.0.3 (16/10/2007) A.k.a The Jones Revision
 * 					- Fixed loose type comparisons 
 * 					- Now utilises multidimensional cache array for temp storage
 * 					- Slight regex modification
 * 					- Doh, preg_quote() to the rescue!
 * 					- Added checks against safe wrapping tags for parameter input
 * 					- Added some default variables as class properties (nearer the top) for easier
 * 					  editing should it be required
 * 					- Fixed a possible issue where $LOC->now was ahead of the time logged in the
 * 					  database. The search module uses the time() function (aha!), so 'll just use
 * 					  that if $LOC->now isn't equal.
 * 					- Fixed an issue with search result pagination altering the unique_id segment
 * 					  on subsequent pages.
 * 				1.0.3 -> 1.0.4 (2/04/2010)
 * 					- Fixed a problem where the search term could conflict with prepend/append/wrap
 * 					  tags and as a result malform the final output.
 * 				2.0 (10/02/2012)
 * 					- Updated for EE2
 **/

$plugin_info = array(
	'pi_name'			=> 'Excerpt',
	'pi_version'		=> '2',
	'pi_author'			=> 'Zoom Studios / Dan Walton',
	'pi_author_url'		=> 'http://www.zoomstudios.co.uk/expression-engine/plugins/excerpt.html',
	'pi_description'	=> 'Highlight search terms in context. EE, Googleised!',
	'pi_usage'			=> Zm_excerpt::usage()
);

class Zm_excerpt {

	//////
	//
	//  Feel free to edit these to your hearts desire. But please consider very carefully which
	//  safe wrap tags you choose to include especially if you are taking any user input as plugin
	//  parameters!!
	//
	//////

	// This list of mostly sane html tags are used to validate parameter input against.
	var $safe_wrap_tags = array(
		'a', 'abbr', 'acronym', 'address',
		'b', 'big', 'blockquote',
		'caption', 'center', 'cite', 'code',
		'dd', 'del', 'div', 'dfn', 'dl', 'dt',
		'em',
		'fieldset', 'font',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
		'i', 'ins',
		'kbd',
		'label', 'legend', 'li',
		'ol', 'optgroup', 'option',
		'p', 'pre',
		'q',
		'samp', 'select', 'small', 'span', 'strike', 'strong', 'sub', 'sup', /* whaasup!? */
		'table', 'tbody', 'td', 'textarea', 'tfoot', 'th', 'thead', 'title',
		'tr', 'tt', 'ul',
		'var'
	);

	// Default wrapping tags
	var $default_wrap = 'strong';
	var $default_chunk_wrap = 'span';
	var $default_output_wrap = '';

	// Default prepend/append content
	var $default_chunk_prepend = '';
	var $default_chunk_append = '... ';
	var $default_output_prepend = '';
	var $default_output_append = '';

	// Some default numbers
	var $default_pre_chars = 50;
	var $default_post_chars = 50;
	var $default_show = 3;

	//////
	//
	//  No editing below here for you!
	//
	//////

	var $return_data;

	function Zm_excerpt() {
		$this->EE =& get_instance();
		
		$this->return_data = $this->EE->TMPL->tagdata; // Added a space because of some PHP PCRE librarys inability to recognise '\A' and '[\^]' (unless im jsut stupid)
		
		//////
		//
		//  First the plugin tests if it has been called before. It need not do any dynamic checks
		//  whatsoever if it has.  If user keywords are supplied (via parameter) it again will skip
		//  the process of determining the logged search term.
		//
		//  It won't cache keywords should they be supplied via the parameter since that would
		//  likely cause unexpected behaviour (and doesn't really save any overhead)
		//
		//////
		
		$keywords = array();
		
		if(!isset($this->EE->session->cache['zm_excerpt']['keywords']) AND !isset($this->EE->session->cache['zm_excerpt']['failed'])) {
			$search_term = $this->EE->TMPL->fetch_param('keywords');
			
			// If false, we have to go looking for them...
			if($search_term === FALSE) {
				// Minimise the size of the search array, about an hour should suffice
				$stamp = ($this->EE->localize->now != time()) ? time() : $this->EE->localize->now;
				$date_threshold = $stamp - 3600;
				
				// Get all of the searches from this IP within the date threshold
				$this->EE->db->select('keywords, search_id');
				
				$query = $this->EE->db->get_where('search', array('ip_address' => $this->EE->input->ip_address(), 'search_date >' => $date_threshold));
				
				// If any results returned, format an array for session storage else just give up
				if($query->num_rows() > 0) {
					foreach($query->result_array() AS $row) {
						$searches[$row['search_id']] = $row['keywords'];
					}
				} else {
					return $this->EE->session->cache['zm_excerpt']['failed'] = TRUE;
				}
				
				// Are we looking at a valid search?
				$i = 0;
				foreach($this->EE->uri->segments AS $segment) {
					// If paginated, the segment might be longer than 32 so strip it off since we
					// don't need to know what page we're looking at
					$segment = substr($segment, 0, 32);
					
					if(isset($searches[$segment])) {
						$search_term = $searches[$segment];
						continue;
					}
					
					// Should we rely on EE's max segment quantity? paranoia
					if($i == 20) {
						return $this->EE->session->cache['zm_excerpt']['failed'] = TRUE;
					}
					$i++;
				}
				
				// No dice?
				if(!isset($search_term)) {
					return $this->EE->session->cache['zm_excerpt']['failed'] = TRUE;
				}
			}
			
			// Tokenise the term so that we can construct interesting regular expressions
			$invalid = "\40\41\42\43\44\45\46\47\48\49\50\51\52\53 \54\55\56\57\72\73\74\75\76 \77\100\133\134\135\136\137\138\139\140 \173\174\175\176\n\r\t";
			
			$token = strtok($search_term, $invalid);
			while($token) {
				$keywords[] = $token;
				$token = strtok($invalid);
			}
			
			// Could it be possible that there are no valid keywords for this search? better safe
			// than sorry...
			if(count($keywords) == 0) {
				return $this->EE->session->cache['zm_excerpt']['failed'] = TRUE;
			}
			
			// Store for later
			if(!$this->EE->TMPL->fetch_param('keywords')) {
				$this->EE->session->cache['zm_excerpt']['keywords'] = $keywords;
			}
		} else {
			// We've been here before, and failed miserably :(
			if(isset($this->EE->session->cache['zm_excerpt']['failed'])) {
				return;
			}
			$keywords = $this->EE->session->cache['zm_excerpt']['keywords'];
		}
		//////
		//
		//  We now have something useable in $keywords. The plugin can now continue on with it's
		//  primary job of highlighting words. Fun?
		//
		//////
		
		// Get parameters
		switch(in_array(strtolower($this->EE->TMPL->fetch_param('wrap')), $this->safe_wrap_tags)) {
			case TRUE:
				$wrap = $this->EE->TMPL->fetch_param('wrap');
				break;
			default:
				$wrap = $this->default_wrap;
				break;
		}
		
		$chunk_prepend = ($this->EE->TMPL->fetch_param('chunk_prepend') === FALSE) ? $this->default_chunk_prepend : $this->EE->TMPL->fetch_param('chunk_prepend');
		$chunk_append = ($this->EE->TMPL->fetch_param('chunk_append') === FALSE) ? $this->default_chunk_append : $this->EE->TMPL->fetch_param('chunk_append');
		switch(in_array(strtolower($this->EE->TMPL->fetch_param('chunk_wrap')), $this->safe_wrap_tags)) {
			case TRUE:
				$chunk_wrap = $this->EE->TMPL->fetch_param('chunk_wrap');
				break;
			default:
				$chunk_wrap = $this->default_chunk_wrap;
				break;
		}
		
		$output_prepend = ($this->EE->TMPL->fetch_param('output_prepend') === FALSE) ? $this->default_output_prepend : $this->EE->TMPL->fetch_param('output_prepend');
		$output_append = ($this->EE->TMPL->fetch_param('output_append') === FALSE) ? $this->default_output_append : $this->EE->TMPL->fetch_param('output_append');
		switch(in_array(strtolower($this->EE->TMPL->fetch_param('output_wrap')), $this->safe_wrap_tags)) {
			case TRUE:
				$output_wrap = $this->EE->TMPL->fetch_param('output_wrap');
				break;
			default:
				$output_wrap = $this->default_output_wrap;
				break;
		}
		
		$pre_chars = ($this->EE->TMPL->fetch_param('pre_chars') === FALSE) ? $this->default_pre_chars : explode('-', $this->EE->TMPL->fetch_param('pre_chars'));
		$post_chars = ($this->EE->TMPL->fetch_param('post_chars') === FALSE) ? $this->default_post_chars : explode('-', $this->EE->TMPL->fetch_param('post_chars'));
		$show = ($this->EE->TMPL->fetch_param('chunks') === FALSE) ? $this->default_show : $this->EE->TMPL->fetch_param('chunks');
		$sort = $this->EE->TMPL->fetch_param('sort');
		$order = $this->EE->TMPL->fetch_param('order');
		
		// If we have ranges for pre/post characters we randomise them
		if(count($pre_chars) == 2) {
			$pre_chars = mt_rand($pre_chars[0], $pre_chars[1]);
		}
		if(count($post_chars) == 2) {
			$post_chars = mt_rand($post_chars[0], $post_chars[1]);
		}
		
		// Strip inner tags
		$this->return_data = preg_replace('@<(?:/?|&#47;?)\w+((\s+\w+(\s*=\s*(?:".*?"|\'.*?\'|[^\'">\s]+))?)+\s*|\s*)(?:/?|&#47;?)>@', '', $this->return_data);
		
		// Build regex pattern for main chunk highlighting
		$regex = '/\s(?:.){0,'.preg_quote($pre_chars, '/').'}(?:';
		$replace = array();
		foreach($keywords AS $word) {
			$regex .= '\W'.preg_quote($word, '/').'\W|';
			$replace[] = $word;
		}
		$regex = substr($regex, 0, -1);
		$regex .= ')(?:.){0,'.preg_quote($post_chars, '/').'}\s/mi';
		
		// Get relevant chunks.
		preg_match_all($regex, $this->return_data, $chunks);
		
		// Nothing found? get out whilst we can
		if(!isset($chunks[0])) {
			return;
		}
		
		$chunks_found = count($chunks[0]);
		
		if($show == 'all') {
			$do = $chunks_found;
		} else {
			$do = ($show <= $chunks_found) ? $show : $chunks_found;
		}
		
		// Are we ordering?
		switch($order) {
			case 'random' :
				shuffle($chunks[0]); // Would array_rand() be more efficient? Benchmarking needed
				break;
			default :
				// Do nothing yet
				break;
		}

		// Are we sorting the chunks?
		if($sort == 'desc') {
			$chunks[0] = array_reverse($chunks[0]);
		}
		
		// Build the chunk wrapper
		$chunk_open = ($chunk_wrap == 'none') ? '' : '<'.$chunk_wrap.'>';
		$chunk_close = ($chunk_wrap == 'none') ? '' : '</'.$chunk_wrap.'>';
		
		// Highlight matches in each chunk
		$highlightedChunks = array();
		$chunkIndex = 0;
		while($do > 0) {
			$highlightedChunks[] = preg_replace('/('.preg_quote(implode('|', $replace), '/').')/i', '<'.$wrap.'>\\1</'.$wrap.'>', $chunks[0][$chunkIndex]);
			$chunkIndex++;
			$do--;
		}
		
		// Concatenate highlighted chunks and prepare final string for returning
		$return = '';
		if ($chunkIndex > 0) {
			$return = ($output_wrap != '') ? '<'.$output_wrap.'>'.$output_prepend : '';
			foreach($highlightedChunks as $chunk) {
				$return .= $chunk_open.$chunk_prepend.trim($chunk).$chunk_append.$chunk_close;
			}
			$return .= ($output_wrap != '') ? $output_append.'</'.$output_wrap.'>' : '';
		}
		return $this->return_data = $return;
	}
	
	function usage() {
		ob_start(); 
		?>
		
		Usage:
		--------------------------------
		Wrap this plugin around the {full_text} variable on your search results template.
		
		Alternatively you can wrap this plugin around any text, and supply words to highlight.
		
		Parameters:
		--------------------------------
		wrap=""
		
		The html tag name (don't include the < > parts) to wrap around each highlighted word. The
		default value is "strong".
		
		chunk_wrap=""
		
		The html tag name (don't include the < > parts) to wrap around each relevant 'chunk'
		extracted from the entire {full_text} value. The default value is "span". If supplied with
		"none", nothing will be wrapped around each chunk.
		
		chunk_prepend=""
		
		Data to prepend to each individual chunk returned. This data will be inserted inside the
		chunk_wrap tags.
		
		chunk_append=""
		
		Like the chunk_prepend parameter, but this inserts the data immediately before the closing
		chunk_wrap tag. Defaults to '... '.
		
		output_wrap=""
		
		The html tag name (don't include the < > parts) to wrap around the entire output. Defaults
		to nothing.
		
		output_prepend=""
		
		Data to insert in the output, before any chunks but after the opening output_wrap tag, if
		supplied.
		
		output_append=""
		
		Like the output_prepend parameter, but this will insert the data immediately before the
		closing output_wrap tag if supplied.
		
		pre_chars=""
		
		The number of characters to include before the highlighted term. Regardless of given value,
		the pointer will not dissect whole words. The default value is "50".  If given a value such
		as "50-100" it will choose a random value between 50 and 100.
		
		post_chars=""
		
		The number of characters to include after the hightlighted term. Regardless of given value,
		the pointer will not dissect whole words. The default value is "50".  If given a value such
		as "50-100" it will choose a random value between 50 and 100.
		
		chunks=""
		
		The number of relevant chunks to return. Values can be numeric or "all". The default value
		is "3". Setting this parameter to all will return all relevant chunks found.
		
		keywords=""
		
		Instead of acting dynamically, the plugin can be given the search term, or any term, via
		this parameter.
		
		sort=""
		
		Sort the chunks ascendingly or descendingly from the order in which they were found. values
		are "asc" or "desc". Default is "asc".
		
		order=""
		
		Order the chunks. Currently this parameter accepts only "random".
		
		Additional Notes:
		--------------------------------
		The three wrap parameters (wrap, chunk_wrap, output_wrap) are tested against a list of
		deemed 'safe' html tags. Should it fail this test it reverts back to the default tag for
		that parameter. These defaults, as well as the safe list, can be edited. The variables are
		located very near the top of the plugin file just below the line that looks like:
		"class Zm_excerpt".
		
		<?php
		$buffer = ob_get_contents();
		ob_end_clean(); 
		return $buffer;
	}
}

?>