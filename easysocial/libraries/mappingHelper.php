<?php

/**
 * @package API plugins
 * @copyright Copyright (C) 2009 2014 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license GNU GPLv2 <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 * @link http://www.techjoomla.com
*/
defined('_JEXEC') or die('Restricted access');

jimport( 'libraries.schema.group' );
jimport( 'joomla.filesystem.file' );

require_once JPATH_ADMINISTRATOR.'/components/com_easysocial/includes/foundry.php';
require_once JPATH_ADMINISTRATOR.'/components/com_easysocial/models/groups.php';
require_once JPATH_ADMINISTRATOR.'/components/com_easysocial/models/covers.php';
require_once JPATH_ADMINISTRATOR.'/components/com_easysocial/models/albums.php';
require_once JPATH_ADMINISTRATOR.'/components/com_easysocial/models/fields.php';

require_once JPATH_SITE.'/plugins/api/easysocial/libraries/schema/group.php';
require_once JPATH_SITE.'/plugins/api/easysocial/libraries/schema/message.php';
require_once JPATH_SITE.'/plugins/api/easysocial/libraries/schema/discussion.php';

require_once JPATH_SITE.'/plugins/api/easysocial/libraries/schema/stream.php';
require_once JPATH_SITE.'/plugins/api/easysocial/libraries/schema/user.php';
require_once JPATH_SITE.'/plugins/api/easysocial/libraries/schema/profile.php';
require_once JPATH_SITE.'/plugins/api/easysocial/libraries/schema/category.php';
require_once JPATH_SITE.'/plugins/api/easysocial/libraries/schema/albums.php';
require_once JPATH_SITE.'/plugins/api/easysocial/libraries/schema/photos.php';
require_once JPATH_SITE.'/plugins/api/easysocial/libraries/schema/createalbum.php';


class EasySocialApiMappingHelper
{
	public $log_user = 0;
	
	public function mapItem($rows, $obj_type='', $userid = 0 , $strip_tags='', $text_length=0, $skip=array()) {
	
		$this->log_user = $userid;

		switch($obj_type)
		{
			case 'category':
						return $this->categorySchema($rows);
						break;
			case 'group':
						return $this->groupSchema($rows,$userid);
						break;
			case 'profile':
						return $this->profileSchema($rows,$userid);
						break;
			case 'fields':
						return $this->fieldsSchema($rows,$userid);
						break;
			case 'user':
						return $this->userSchema($rows);
						break;
			case 'comment':
						return $this->commentSchema($rows);
						break;
			case 'message':
						return $this->messageSchema($rows);
						break;
			case 'conversion':
						return $this->conversionSchema($rows,$userid);
						break;
			case 'reply':
						return $this->replySchema($rows);
						break;
			case 'discussion':
						return $this->discussionSchema($rows);
						break;
			case 'stream':
						return $this->streamSchema($rows,$userid);
						break;
			case 'albums':
						return $this->albumsSchema($rows,$userid);
						break;			
			case 'photos':
						return $this->photosSchema($rows,$userid);
						break;
		}
		
		return $item;
	}
	//To build photo object 
	public function photosSchema($rows,$userid)
	{
		$lang = JFactory::getLanguage();
		$lang->load('com_easysocial', JPATH_ADMINISTRATOR, 'en-GB', true);
		$result = array();		
		foreach($rows as $ky=>$row)
		{
			if(isset($row->id))
			{					
				$item = new PhotosSimpleSchema();	
				$item->isowner= ($row->uid == $userid)?true:false;			
				$item->id = $row->id;
				$item->album_id = $row->album_id;
				$item->cover_id = $row->cover_id;
				$item->type = $row->type;
				$item->uid = $row->id; // for post comment photo id is required.
				$item->user_id = $row->user_id;
				$item->title = JText::_($row->title);
				$item->caption= JText::_($row->caption);
				$item->created=$row->created;				
				$item->state=$row->state;
				$item->assigned_date=$row->assigned_date;
				$item->image_large=$row->image_large;
				$item->image_square=$row->image_square;				
				$item->image_thumbnail=$row->image_thumbnail;				
				$item->image_featured=$row->image_featured;
				$like = FD::photo();
				$like->data->id=$row->id;
				$data = $like->likes();				
				$item->likes=$this->createlikeObj($data,$userid);
				$comobj=$like->comments();
				$comobj->stream_id=1;
				$item->comment_element = $comobj->element.".".$comobj->group.".".$comobj->verb;			
				$item->comments=$this->createCommentsObj($comobj);
				$result[] = $item;
			}
		}
		return $result;
	}
	//to build ablum object
	public function albumsSchema($rows,$userid)	
	{	
		//To load easysocial language constant
		$lang = JFactory::getLanguage();
		$lang->load('com_easysocial', JPATH_ADMINISTRATOR, 'en-GB', true);
		$result = array();
		
		foreach($rows as $ky=>$row)
		{	
			if(isset($row->id))
			{
				$item = new GetalbumsSimpleSchema();
				
				$item->id = $row->id;
				$item->cover_id = $row->cover_id;
				$item->type = $row->type;
				$item->uid = $row->uid;
				$item->title = JText::_($row->title);
				$item->caption= JText::_($row->caption);
				$item->created=$row->created;
				$item->assigned_date=$row->assigned_date;
				$item->cover_featured=$row->cover_featured;
				$item->cover_large=$row->cover_large;
				$item->cover_square=$row->cover_square;
				$item->cover_thumbnail=$row->cover_thumbnail;
				$item->count=$row->count;				
				$likes = FD::likes($row->id, SOCIAL_TYPE_ALBUM , 'create', SOCIAL_APPS_GROUP_USER );				
				$item->likes = $this->createlikeObj($likes,$userid);
			    //$item->total=$item->likes->total;			
				
				// Get album comments
				$comments = FD::comments($row->id, SOCIAL_TYPE_ALBUM , 'create', SOCIAL_APPS_GROUP_USER , array('url' => $row->getPermalink()));				
				$item->comment_element = $comments->element.".".$comments->group.".".$comments->verb;				
				$comments->stream_id=1;
			
				//$comments->element=$item->comment_element;				
				$item->comments = $this->createCommentsObj($comments);				
				$options = array('uid' => $comments->uid, 'element' => $item->comment_element, 'stream_id' => $comments->stream_id);				
				$model  = FD::model('Comments');
				$comcount = $model->getCommentCount($options);	
				$item->commentcount=$comcount;				
				$item->isowner = ( $row->uid == $userid )?true:false;				
				$result[] = $item;
			}
		}
		return $result;		
	}
	
	//To build field object
	public function fieldsSchema($rows,$userid)
	{
		
		$lang = JFactory::getLanguage();
		$lang->load('com_easysocial', JPATH_ADMINISTRATOR, 'en-GB', true);
		//$str = JText::_('COM_EASYSOCIAL_FIELDS_PROFILE_DEFAULT_DESIRED_USERNAME');
		
		if(count($rows)>0)
		{
			$data = array();
			$fmod_obj = new EasySocialModelFields();
			foreach($rows as $row)
			{
				$fobj = new fildsSimpleSchema();
				
				//$fobj->id = $row->id;
				$fobj->field_id = $row->id;
				//$fobj->title = JText($row->title);
				$fobj->title = JText::_($row->title);
				$fobj->field_name = JText::_($row->title);
				$fobj->step = $row->step_id;
				$fobj->field_value = $fmod_obj->getCustomFieldsValue($row->id,$userid , SOCIAL_FIELDS_GROUP_USER);
				
				
				if($fobj->field_name == 'Gender' &&  $fobj->field_value != null )
				{
					$fobj->field_value = ( $fobj->field_value == 1 )?'male':'female';
				}
				
				//to manage address as per site
				if( $fobj->unique_key == 'ADDRESS' )
				{
					//$fobj->field_value = $row->data['address'];
					$fobj->field_value = $row->data['state'].','.$row->data['country'];
				}
				
				$fobj->params = json_decode($row->params);
				
				$data[] = $fobj; 
			}
			
			return $data;
		}
	}
	//function for stream main obj
	public function streamSchema($rows,$userid) 
	{
		$result = array();
		
		foreach($rows as $ky=>$row)
		{
			if(isset($row->uid))
			{
				$item = new streamSimpleSchema();

				//new code
				// Set the stream title
				$item->id = $row->uid;
				
				//$item->title = strip_tags($row->title);
				//code changed as request not right way
				$item->title = $row->title;
				if($row->type != 'links')
				{
					$item->title = str_replace('href="','href="'.JURI::root(),$item->title);
				}
				$item->type = $row->type;
				$item->group = $row->cluster_type;
				$item->element_id = $row->contextId;
				//
				$item->content = $row->content;
				
				//$item->preview = $row->preview;
				
				//hari - code for build video iframe
				//check code optimisation
				if($row->preview)  
                {
                    $frame_match= preg_match('/;iframe.*?>/', $row->preview);
                }    
                else
                {
                    $frame_match= preg_match('/;iframe.*?>/', $row->content);  
                    $row->preview = $row->content; 
                    $item->content = null;                 
                }
                
                if($frame_match)
				   {
					   $dom = new DOMDocument;
					   $dom->loadHTML($row->preview);
					   foreach ($dom->getElementsByTagName('a') as $node) {
							$vurls = $node->getAttribute( 'href' );
							                   
							if(preg_match('/com_easysocial/', $vurls))
							{
								continue;
							}
							else
							{
								$first = $vurls;
								//all video data
								$url_data = $this->crawlLink($vurls);
								$item->preview = '<div class="video-container">'.$url_data->oembed->html.'</div>';
	
								break;        
							}                                
					   }
					   
				   }
				   else
				   {
						   $item->preview = $row->preview;
				   }
				//end
				
				// Set the stream content
				if(!empty($item->preview))
				{
					$item->raw_content_url = $row->preview;
				}
				elseif(!empty($item->content))
				{
					$item->raw_content_url = $row->content;
				}
				
				if($row->type != 'links')
				{				
					$item->raw_content_url = str_replace('href="','href="'.JURI::root(),$item->raw_content_url);
				}
				// Set the publish date
				$item->published = $row->created->toMySQL();
				
				/*
				// Set the generator
				$item->generator = new stdClass();
				$item->generator->url = JURI::root();

				// Set the generator
				$item->provider = new stdClass();
				$item->provider->url = JURI::root();
				*/
				// Set the verb
				$item->verb = $row->verb;

				//create users object
				$actors = array();
				$user_url = array();
				foreach($row->actors as $actor)
				{
					$user_url[$actor->id] = JURI::root().FRoute::profile( array('id' => $actor->id , 'layout' => 'item', 'sef' => false ));
					$actors[] = $this->createUserObj($actor->id); 
				}
				
				//with share obj users object
				//$with_usr = array();
				$with_user_url = array();
				
				foreach($row->with as $actor)
				{
					$withurl = JURI::root().FRoute::profile( array('id' => $actor->id , 'layout' => 'item', 'sef' => false ));
					$with_user_url[] = "<a href='".$withurl."'>".$actor->username."</a>";
					
					//$with_url = $with_url." and ".
					
					//$with_user_url[] = $this->createUserObj($actor->id); 
				}
				$item->with = null;
				//to maintain site view for with url
				if( !empty($with_user_url) )
			   {
				   $cnt = sizeof($with_user_url);                                                                                
				   $item->with = 'with '.$with_user_url[0];
																				   
				   for($i=0;$i<$cnt-2;$i++)
				   {                                                
						   $item->with = $item->with.', '.$with_user_url[$i+1];                                        
				   }
				   if($cnt-1 != 0)
				   {
						   $item->with =  $item->with.' and '.$with_user_url[$cnt-1];                                        
				   }  
			   }
				
                //
				$item->actor = $actors;

				$item->likes = (!empty($row->likes))?$this->createlikeObj($row->likes,$userid):null;
				
				if(!empty($row->comments->element))
				{
					$item->comment_element = $row->comments->element.".".$row->comments->group.".".$row->comments->verb;
				}
				else
				{
					$item->comment_element = null;
				}
				
				$item->comments = (!empty($row->comments->uid))?$this->createCommentsObj($row->comments):null;
				
				// These properties onwards are not activity stream specs
				$item->icon = $row->fonticon;

				// Set the lapsed time
				$item->lapsed = $row->lapsed;

				// set the if this stream is mini mode or not.
				// mini mode should not have any actions such as - likes, comments, share and etc.
				$item->mini = $row->display == SOCIAL_STREAM_DISPLAY_MINI ? true : false;
				
				//build share url use for share post through app
				$sharing = FD::get( 'Sharing', array( 'url' => FRoute::stream( array( 'layout' => 'item', 'id' => $row->uid, 'external' => true, 'xhtml' => true ) ), 'display' => 'dialog', 'text' => JText::_( 'COM_EASYSOCIAL_STREAM_SOCIAL' ) , 'css' => 'fd-small' ) );
				$item->share_url = $sharing->url;
				
				// Check if this item has already been bookmarked
				$sticky = FD::table('StreamSticky');
				$item->isPinned = null;
				if($sticky)
				{	
					$item->isPinned = $sticky->load(array('stream_id' => $row->uid));
				}
				
				//create urls for app side mapping
				//$log_usr = FRoute::profile( array('id' => $row->uid , 'layout' => 'item', 'sef' => false ));
				$strm_urls = array();
				
				$strm_urls['actors'] = $user_url;
				
				/*switch( $row->type )
				{
					case 'discussions': $strm_urls['discussions'] = JURI::root().FRoute::apps( array('id' => $row->uid , 'layout' => 'canvas', 'sef' => false ));
					//FRoute::apps( array( 'layout' => 'canvas' , 'customView' => 'item' , 'uid' => $group->getAlias() , 'type' => SOCIAL_TYPE_GROUP , 'id' => $this->getApp()->getAlias() , 'discussionId' => $discussion->id ) , false );
									break;
					case 'apps':	$strm_urls['apps'] = JURI::root().FRoute::apps( array('id' => $row->element_id , 'layout' => 'item', 'sef' => false ));
									break;
					case 'dashboard':	$strm_urls['dashboard'] = JURI::root().FRoute::dashboard( array('id' => $row->element_id , 'layout' => 'item', 'sef' => false ));
									break;
					case 'albums':	$strm_urls['album'] = JURI::root().FRoute::albums( array('id' => $row->element_id , 'layout' => 'item', 'sef' => false ));
									break;
					case 'photos':	$strm_urls['photos'] = JURI::root().FRoute::photos( array('id' => $row->element_id , 'layout' => 'item', 'sef' => false ));
									break;
					case 'groups':	$strm_urls['groups'] = JURI::root().FRoute::groups( array('id' => $row->element_id , 'layout' => 'item', 'sef' => false ));
									break;
					case 'links':	$lnk_arr = explode('shared', $item->title);
									
									preg_match_all('/href=\"(.*?)\"/i', $lnk_arr[1], $matches);
									$strm_urls['links'] = $matches[1][0];
									break;
				}
				
				$item->strm_urls = $strm_urls; */
				$result[]	= $item;
				//$result[]	= $row;
				//end new
			
			}
		}

		return $result;
	}
	
	//crowlink link data for video post
	public function crawlLink($url)
	{
		// Get the crawler
		$crawler = FD::get('crawler');
		// Result placeholder
		$result = null;
		
			// Generate a hash for the url
			$hash = md5($url);

			$link = FD::table('Link');
			$exists = $link->load(array('hash' => $hash));

			// If it doesn't exist, store it.
			if (!$exists) {

				$crawler->crawl($url);

				// Get the data from our crawler library
				$data = $crawler->getData();

				// Now we need to cache the link so that the next time, we don't crawl it again.
				$link->hash = $hash;
				$link->data = json_encode($data);
				$link->store();
			}

		$result = json_decode($link->data);

		return $result;
	}
	
	
	//create like object
	public function createLikeObj($row,$userid)
	{
		$likesModel = FD::model('Likes');
		if (!is_bool($row->uid)) {
	
			// Like id should contain the exact item id
			$item = new likesSimpleSchema();
			
			$key = $row->element.'.'.$row->group.'.'.$row->verb;

			$item->uid = $row->uid;
			$item->element = $row->element;
			$item->group = $row->group;
			$item->verb = $row->verb;
			
			$item->hasLiked = $likesModel->hasLiked($row->uid,$key,$userid,$row->stream_id);
			$item->stream_id = $row->stream_id;

			// Get the total likes
			$item->total = $likesModel->getLikesCount($row->uid, $key);
			$item->like_obj = $likesModel->getLikes($row->uid,$key);
			
			return $item;
		}
		return null;
	}
	
	//create comments object
	public function createCommentsObj($row,$limitstart=0,$limit=10)
	{

		if (!is_bool($row->uid))
		{
			$options = array('uid' => $row->uid, 'element' => $row->element, 'stream_id' => $row->stream_id, 'start' => $limitstart, 'limit' => $limit);

			$model  = FD::model('Comments');

			$result = $model->getComments($options);

			$data = array();
			
			$data['base_obj'] = $row;
			
			$likesModel = FD::model('Likes');
			
			foreach($result As $cdt)
			{
				$item = new commentsSimpleSchema();
				
				$row->group = (isset($row->group))?$row->group:null;
				$row->verb = (isset($row->group))?$row->verb:null;

				$item->uid = $cdt->id;
				$item->element = $cdt->element;
				$item->element_id = $row->uid;
				$item->stream_id = $cdt->stream_id;
				$item->comment = $cdt->comment;
				$item->type = $row->element;
				$item->verb = $row->verb;
				$item->group = $row->group;
				$item->created_by = $this->createUserObj($cdt->created_by);
				$item->created = $cdt->created;

				$item->likes   = new likesSimpleSchema();
				$item->likes->uid     = $cdt->id;
				$item->likes->element = 'comments';
				$item->likes->group   = 'user';
				$item->likes->verb    = 'like';
				$item->likes->stream_id = $cdt->stream_id;
				$item->likes->total   = $likesModel->getLikesCount($item->uid, 'comments.' . 'user' . '.like');
				$item->likes->hasLiked = $likesModel->hasLiked($item->uid,'comments.' . 'user' . '.like',$cdt->created_by);
				$data['data'][] = $item;
			}
			
			return $data;
		}
		
		return null;
	}
	
	//function for discussion main obj
	public function discussionSchema($rows) 
	{
		//$conv_model = FD::model('Conversations');
		$result = array();

		foreach($rows as $ky=>$row)
		{
			if(isset($row->id))
			{

				$item = new discussionSimpleSchema();

				$item->id = $row->id;
				$item->title = $row->title;
				$item->description = $row->content;
				//$item->attachment = $conv_model->getAttachments($row->id);
				$item->created_by = $this->createUserObj($row->created_by);
				$item->created_date = $this->dateCreate($row->created);
				$item->lapsed = $this->calLaps($row->created);
				$item->hits = $row->hits;
				$item->replies_count = $row->total_replies;
				$item->last_replied = $this->calLaps($row->last_replied);
				//$item->replies = 0;
				$last_repl = (isset($row->lastreply))?array(0=>$row->lastreply):array();
				
				$item->replies = $this->discussionReply($last_repl);
				
				$result[] = $item;
			}
		}

		return $result;
	}
	
	//function for discussion reply obj
	public function discussionReply($rows) 
	{
		if(empty($rows))
		return 0;
		//$conv_model = FD::model('Conversations');
		$result = array();
		
		foreach($rows as $ky=>$row)
		{
			if(isset($row->id))
			{
				$item = new discussionReplySimpleSchema();

				$item->id = $row->id;
				$item->reply = $row->content;
				$item->created_by = $this->createUserObj($row->created_by);
				$item->created_date = $this->dateCreate($row->created);
				$item->lapsed = $this->calLaps($row->created);
				
				$result[] = $item;
			}
		}

		return $result;
	}
	
	//function for create category schema
	public function categorySchema($rows) 
	{
		$result = array();
		
		foreach($rows as $ky=>$row)
		{
			if(isset($row->id))
			{
				$item = new CategorySimpleSchema();

				$item->categoryid = $row->id;
				$item->title = $row->title;
				$item->description = $row->description;
				$item->state = $row->state;
				//$item->attachment = $conv_model->getAttachments($row->id);
				$item->created_by = $this->createUserObj($row->uid);
				$item->created_date = $this->dateCreate($row->created);

				$result[] = $item;
			}
		}
		
		return $result;
	}
	
	//function for create group schema
	public function groupSchema($rows=null,$userid=0) 
	{
		if($rows == null || $userid == 0)
		{
			$ret_arr = new stdClass;
			$ret_arr->status = false;
			$ret_arr->message = "No group found in search";
			
			return $ret_arr;
		}

		$result = array();
		$user = JFactory::getUser($userid);

		foreach($rows as $ky=>$row)
		{
			if(isset($row->id))
			{
				$grpobj = FD::group( $row->id );
				$item = new GroupSimpleSchema();
				
				$item->id = $row->id;
				$item->title = $row->title;
				$item->alias = $row->alias;
				$item->description = $row->description;
				$item->hits = $row->hits;
				$item->state = $row->state;
				$item->created_date = $this->dateCreate($row->created);
				
				//get category name
				$category 	= FD::table('GroupCategory');
				$category->load($row->category_id);
				$item->category_id = $row->category_id;
				$item->category_name = $category->get('title');
				$item->cover = $grpobj->getCover();

				$item->created_by = $row->creator_uid;
				$item->creator_name = JFactory::getUser($row->creator_uid)->username;
				//$item->type = ($row->type == 1 )?'Public':'Public';
				$item->type = $row->type;
				$item->params = $row->params;
			
				foreach($row->avatars As $ky=>$avt)
				{
					$avt_key = 'avatar_'.$ky;
					$item->$avt_key = JURI::root().'media/com_easysocial/avatars/group/'.$row->id.'/'.$avt;
										
					$fst = JFile::exists('media/com_easysocial/avatars/group/'.$row->id.'/'.$avt);
					//set default image
					if(!$fst)
					{
						$item->$avt_key = JURI::root().'media/com_easysocial/avatars/group/'.$ky.'.png';
					}
				}
				
				//$obj->members = $row->members;
				$grp_obj = FD::model('Groups');
				$item->member_count = $grp_obj->getTotalMembers($row->id);
				//$obj->cover = $grp_obj->getMeta($row->id);
				
				$alb_model = FD::model('Albums');
				
				$uid = $row->id.':'.$row->title;

				$filters = array('uid'=>$uid,'type'=>'group');
				//get total album count
				$item->album_count = $alb_model->getTotalAlbums($filters);

				//get group album list
				//$albums = $alb_model->getAlbums($uid,'group');
				
				$item->isowner = ( $row->creator_uid == $userid )?true:false;
				$item->ismember = in_array( $userid,$row->members );
				$item->approval_pending = in_array( $userid,$row->pending );

				$result[] = $item;
			}
		}
		return $result;
		
	}
	
	//function for create profile schema
	public function profileSchema($other_user_id,$userid) 
	{

		$log_user_obj = FD::user($userid);
		$other_user_obj = FD::user($other_user_id);
		
		$user_obj = $this->createUserObj($other_user_id);
		$user_obj->isself = ($userid == $other_user_id )?true:false;
		$user_obj->cover = $other_user_obj->getCover();

		if( $userid != $other_user_id )
		{
			$frnd_mod = FD::model( 'Friends' );
			$trg_obj = FD::user( $other_user_id );
			$user_obj->isfriend = $trg_obj->isFriends( $userid );
			$user_obj->isfollower = $trg_obj->isFollowed( $userid );
			$user_obj->approval_pending = $frnd_mod->isPendingFriends($userid,$other_user_id);

			//$user_obj->approval_pending = $user->isPending($other_user_id);
		}
		$user_obj->friend_count = $other_user_obj->getTotalFriends();
		$user_obj->follower_count = $other_user_obj->getTotalFollowers();
		$user_obj->badges = $other_user_obj->getBadges();
		$user_obj->points = $other_user_obj->getPoints();
		
		
		return $user_obj;
	}
	
	//function for create user schema
	public function userSchema($rows) 
	{
		$data = array();
		foreach($rows as $row)
		{
			$data[] = $this->createUserObj($row->id);
		}
		
		return $data;
	}
	
	//function for create message schema
	public function conversionSchema($rows,$log_user) 
	{
		$conv_model = FD::model('Conversations');
		$result = array();
		
		foreach($rows as $ky=>$row)
		{
			if(isset($row->id))
			{
				$item = new converastionSimpleSchema();
				$participant_usrs = $conv_model->getParticipants( $row->id );
				$con_usrs = array();

				foreach($participant_usrs as $ky=>$usrs)
				{
					if($usrs->id && ($log_user != $usrs->id) )
					$con_usrs[] =  $this->createUserObj($usrs->id);
				}
					
				$item->conversion_id = $row->id;
				$item->created_date = $row->created;
				$item->lastreplied_date = $row->lastreplied;
				$item->isread = $row->isread;
				$item->messages = $row->message;
				$item->lapsed = $this->calLaps($row->created);
				$item->participant = $con_usrs;

				$result[] = $item;
			}
		}

		return $result;
	}
	
	//function for create message schema
	public function messageSchema($rows) 
	{
		$conv_model = FD::model('Conversations');
		$result = array();
		
		foreach($rows as $ky=>$row)
		{
			if(isset($row->id))
			{
				$item = new MessageSimpleSchema();

				$item->id = $row->id;
				$item->message = $row->message;
				$item->attachment = null;
				//$item->attachment = $conv_model->getAttachments($row->id);
				$item->created_by = $this->createUserObj($row->created_by);
				$item->created_date = $this->dateCreate($row->created);
				$item->lapsed = $this->calLaps($row->created);
				$item->isself = ($this->log_user == $row->created_by)?1:0;
							
				$result[] = $item;
			}
		}

		return $result;
	}
	
	//function for create message schema
	public function replySchema($rows) 
	{
		//$conv_model = FD::model('Conversations');
		$result = array();
		
		foreach($rows as $ky=>$row)
		{
			if(isset($row->id))
			{
				$item = new ReplySimpleSchema();

				$item->id = $row->id;
				$item->reply = $row->content;
				$item->created_by = $this->createUserObj($row->created_by);
				$item->created_date = $this->dateCreate($row->created);
				$item->lapsed = $this->calLaps($row->created);
				$result[] = $item;
			}
		}

		return $result;
	}
	
	//calculate laps time
	function calLaps($date)
	{
		if(strtotime($date) == 0)
		{
			return 0;
		}
		
		return $lap_date = FD::date($date)->toLapsed();
		
	}
	
	//create user object
	public function createUserObj($id){
		
		$user = FD::user($id);
		/*
		$actor = new stdClass;
		
		$image = new stdClass;
		
		$actor->id = $id;
		$actor->username = $user->username;
		
		$image->image_small = $user->getAvatar('small');
		$image->image_medium = $user->getAvatar();
		$image->image_large = $user->getAvatar('large');
		
		$image->cover_image = $user->getCover();
		
		$actor->image = $image;
		
		return $actor;
		*/
		
		$actor = new userSimpleSchema();
		$image = new stdClass;
		
		$actor->id = $id;
		$actor->username = $user->username;
		$actor->name = $user->name;
		
		$image->image_small = $user->getAvatar('small');
		$image->image_medium = $user->getAvatar();
		$image->image_large = $user->getAvatar('large');
		$image->image_square = $user->getAvatar('square');
		
		//set default image
		/*if(!file_exists($image->image_small))
		{
			$image->image_small = JURI::root().'media/com_easysocial/avatars/user/small.png';
			$image->image_medium = JURI::root().'media/com_easysocial/avatars/user/medium.png';
			$image->image_large = JURI::root().'media/com_easysocial/avatars/user/large.png';
		}*/
		
		$image->cover_image = $user->getCover();
		
		$actor->image = $image;
		
		return $actor;
				
		}
	
	public function dateCreate($dt) {

			$date=date_create($dt);
			return $newdta = date_format($date,"l,F j Y");
	}
	
	public function sanitize($text) {
		$text = htmlspecialchars_decode($text);
		$text = str_ireplace('&nbsp;', ' ', $text);
		
		return $text;
	}
	
	public function frnd_nodes($data,$user)
	{
		//print_r($this->plugin->get('user')->id);die();
		//$user = JFactory::getUser($this->plugin->get('user')->id);
		$frnd_mod = FD::model( 'Friends' );
		$list = array();
		foreach($data as $k=>$node)
		{
			//~ print_r($node);
			//~ die();
			if($node->id != $user->id)
			{								
				$node->mutual = $frnd_mod->getMutualFriendCount($user->id,$node->id);
				$node->isFriend = $frnd_mod->isFriends($user->id,$node->id);
				$node->approval_pending = $frnd_mod->isPendingFriends($user->id,$node->id);			
			}
		}		
		return $data;	
	 }	
	
	
}
