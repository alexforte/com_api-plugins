<?php
/**
 * @package API plugins
 * @copyright Copyright (C) 2009 2014 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license GNU GPLv2 <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 * @link http://www.techjoomla.com
*/

defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.plugin.plugin');
jimport('joomla.html.html');

require_once JPATH_ADMINISTRATOR.'/components/com_easysocial/includes/foundry.php';
require_once JPATH_ADMINISTRATOR.'/components/com_easysocial/includes/story/story.php';
require_once JPATH_ADMINISTRATOR.'/components/com_easysocial/includes/photo/photo.php';
require_once JPATH_ADMINISTRATOR.'/components/com_easysocial/includes/crawler/crawler.php';
require_once JPATH_ADMINISTRATOR.'/components/com_easysocial/models/groups.php';
require_once JPATH_ADMINISTRATOR.'/components/com_easysocial/models/covers.php';
require_once JPATH_ADMINISTRATOR.'/components/com_easysocial/models/albums.php';

require_once JPATH_SITE.'/plugins/api/easysocial/libraries/mappingHelper.php';

class EasysocialApiResourceShare extends ApiResource
{
	public function get()
	{
		$this->plugin->setResponse('Use post method to share');
	}

	public function post()
	{
		$app = JFactory::getApplication();

		//$share_for = $app->input->get('share_for','','CMD');
		
		$type = $app->input->get('type','story','STRING');
		
		$content = $app->input->get('content','','RAW');

		//$targetId = $app->input->get('target_user','All','raw');
		$targetId = $app->input->get('target_user',0,'INT');
		
		$cluster = $app->input->get('group_id',null,'INT');
		
		$friends_tags = $app->input->get('friends_tags',null,'ARRAY');
		
		$urls = $app->input->get('urls','','ARRAY');
		
		$log_usr = intval($this->plugin->get('user')->id);
		//now take login user stream for target
		$targetId = ( $targetId != $log_usr )?$targetId:$log_usr;
		
		if($type == 'links')
		{
			//$urls = explode(',',$content);
			$content = $urls[count($urls)-1];
			$url_data = $this->crawlLink($urls);
		}

		$valid = 1;
		$result = new stdClass;
		
		$story = FD::story(SOCIAL_TYPE_USER);
		
		// Check whether the user can really post something on the target
		if ($targetId) {
			$tuser = FD::user($targetId);
			$allowed = $tuser->getPrivacy()->validate('profiles.post.status', $targetId, SOCIAL_TYPE_USER);

			if (!$allowed) {

				$result->id = 0;
				$result->status  = 0;
				$result->message = 'User not allowed any post in share';
				$valid = 0;
			}
		}
		
		if(empty($type))
		{
			$result->id = 0;
			$result->status  = 0;
			$result->message = 'Empty type not allowed';
			$valid = 0;
		}
		else if($valid)
		{
			// Determines if the current posting is for a cluster
			$cluster = isset($cluster) ? $cluster : 0;
			$clusterType = ($cluster) ? 'group' : null;
			$isCluster = $cluster ? true : false;

			if ($isCluster) {
				
				$group = FD::group($cluster);
				$permissions = $group->getParams()->get('stream_permissions', null);

				if($permissions != null)
				{
					// If the user is not an admin, ensure that permissions has member
					if ($group->isMember() && !in_array('member', $permissions) && !$group->isOwner() && !$group->isAdmin()) {
						$result->message = 'This group memder do not have share data permission';
					}

					// If the user is an admin, ensure that permissions has admin
					if ($group->isAdmin() && !in_array('admin', $permissions) && !$group->isOwner()) {
						$result->message = 'This group admin do not have share data permission';
					}
					
					$result->id = 0;
					$result->status  = 0;
					$this->plugin->setResponse($result);
					return;
				}
			}
			
		//validate friends 
			
		$friends = array();

		if (!empty( $friends_tags )) {
			
			// Get the friends model
			$model = FD::model('Friends');

			// Check if the user is really a friend of him / her.
			foreach ($friends_tags as $id) {

				if (!$model->isFriends($log_usr, $id)) {
					continue;
				}

				$friends[]	= $id;
			}
		}
		else
		{
			$friends = null;
		}

		$privacyRule = ( $type == 'photos' ) ? 'photos.view' : 'story.view';
			
			//for hashtag mentions
			$mentions = null;

			//if($type == 'hashtag' || !empty($content))
			if(!empty($content))
			{
				//$type = 'story';
				$start = 0;
				$posn = array();
				
				//code adjust for 0 position hashtag
				$content = 'a '.$content;
				while($pos = strpos(($content),'#',$start))
				{
					//echo 'Found # at position '.$pos."\n";
					$posn[] = $pos - 2;
					$start = $pos+1;
				}
				$content = substr($content, 2);
				//
				//$pos = strpos(($content),'#',$start);
				
				$cont_arr = explode(' ',$content);
				$indx= 0;

				if( $posn[$indx++] != null )
				{
					foreach($cont_arr as $val)
					{
						if(preg_match('/[\'^#,|=_+¬-]/', $val))
						{
							//$vsl = substr_count($val,'#');
							$val_arr = array_filter(explode('#',$val));
		
							foreach($val_arr as $subval)
							{
								$subval = '#'.$subval;
								$mention = new stdClass();
								$mention->start = $posn[$indx++];
								$mention->length = strlen($subval) - 0;
								$mention->value = str_replace('#','',$subval);
								$mention->type = 'hashtag';
								
								$mentions[] = $mention;
							} 
						}
					}
				}

			}
			$contextIds = 0;
			if($type == 'photos')
			{
				$photo_obj = $this->uploadPhoto($log_usr,'user');
				
				$photo_ids[] = $photo_obj->id;
				$contextIds = (count($photo_ids))?$photo_ids:null;
			}
			else
			{
				$type = 'story';
			}
			
			// Process moods here
			$mood = FD::table('Mood');
			/*$hasMood = $mood->bindPost($post);

			// If this exists, we need to store them
			if ($hasMood) {
			$mood->user_id = $this->my->id;
			$mood->store();
			}*/
			
			// Options that should be sent to the stream lib
			$args = array(
							'content' => $content,
							'actorId'		=> $log_usr,
							'targetId'		=> $targetId,
							'location'		=> null,
							'with'			=> $friends,
							'mentions'		=> $mentions,
							'cluster'		=> $cluster,
							'clusterType'	=> $clusterType,
							'mood'			=> $mood,
							'privacyRule'	=> $privacyRule,
							'privacyValue'	=> 'public',
							'privacyCustom'	=> ''
						);

			$photo_ids = array();
			$args['actorId'] = $log_usr;
			$args['contextIds'] = $contextIds;
			$args['contextType']	= $type;
//print_r( $args );die("in share api");
			// Create the stream item
			$stream = $story->create($args);
			
			/*if ($hasMood) {
				$mood->namespace = 'story.user.create';
				$mood->namespace_uid = $stream->id;
				$mood->store();
			}*/
			
			// Privacy is only applicable to normal postings
			if (!$isCluster) {
				$privacyLib = FD::privacy();

				if ($type == 'photos') {

					$photoIds = FD::makeArray($contextIds);

					foreach ($photoIds as $photoId) {
						$privacyLib->add($privacyRule, $photoId, $type, 'public', null, '');
					}
				} else {
					$privacyLib->add($privacyRule, $stream->uid, $type, 'public', null, '');
				}

			}
			// Add badge for the author when a report is created.
			$badge = FD::badges();
			$badge->log('com_easysocial', 'story.create', $log_usr, JText::_('Posted a new update'));

			// @points: story.create
			// Add points for the author when a report is created.
			$points = FD::points();
			$points->assign('story.create', 'com_easysocial', $log_usr);
			
			if($stream->id)
			{
				$result->id = $stream->id;
				$result->status  =1;
				$result->message = 'data share successfully';
			}

		}
		
	   $this->plugin->setResponse($result);
	}
	
	//function for upload photo
	public function uploadPhoto($log_usr=0,$type=null)
	{
		// Get current logged in user.
		$my = FD::user($log_usr);

		// Get user access
		$access = FD::access( $my->id , SOCIAL_TYPE_USER );

		// Load up the photo library
		$lib  = FD::photo($log_usr, $type);
		
		// Define uploader options
		$options = array( 'name' => 'file', 'maxsize' => $lib->getUploadFileSizeLimit() );

		// Get uploaded file
		$file   = FD::uploader($options)->getFile();

		// Load the iamge object
		$image  = FD::image();
		$image->load( $file[ 'tmp_name' ] , $file[ 'name' ] );

		// Detect if this is a really valid image file.
		if( !$image->isValid() )
		{
			return "invalid image";
		}
		
		// Load up the album's model.
		$albumsModel    = FD::model( 'Albums' );

		// Create the default album if necessary
		$album  = $albumsModel->getDefaultAlbum( $log_usr , $type , SOCIAL_ALBUM_STORY_ALBUM );

		// Bind photo data
		$photo              = FD::table( 'Photo' );
		$photo->uid         = $log_usr;
		$photo->type        = $type;
		$photo->user_id     = $my->id;
		$photo->album_id    = $album->id;
		$photo->title       = $file[ 'name' ];
		$photo->caption     = '';
		$photo->state     = 1;
		$photo->ordering    = 0;

		// Set the creation date alias
		$photo->assigned_date   = FD::date()->toMySQL();

		// Trigger rules that should occur before a photo is stored
		$photo->beforeStore( $file , $image );

		// Try to store the photo.
		$state      = $photo->store();
		
		 // Load the photos model
		$photosModel    = FD::model( 'Photos' );

		// Get the storage path for this photo
		$storage    = FD::call( 'Photos' , 'getStoragePath' , array( $album->id , $photo->id ) );

		// Get the photos library
		$photoLib   = FD::get( 'Photos' , $image );
		$paths      = $photoLib->create($storage);
		
		// Create metadata about the photos
		if( $paths )
		{
			foreach( $paths as $type => $fileName )
			{
				$meta               = FD::table( 'PhotoMeta' );
				$meta->photo_id     = $photo->id;
				$meta->group        = SOCIAL_PHOTOS_META_PATH;
				$meta->property     = $type;
				$meta->value        = $storage . '/' . $fileName;

				$meta->store();

				// We need to store the photos dimension here
				list($width, $height, $imageType, $attr) = getimagesize(JPATH_ROOT . $storage . '/' . $fileName);

				// Set the photo dimensions
				$meta               = FD::table('PhotoMeta');
				$meta->photo_id     = $photo->id;
				$meta->group        = SOCIAL_PHOTOS_META_WIDTH;
				$meta->property     = $type;
				$meta->value        = $width;
				$meta->store();

				$meta               = FD::table('PhotoMeta');
				$meta->photo_id     = $photo->id;
				$meta->group        = SOCIAL_PHOTOS_META_HEIGHT;
				$meta->property     = $type;
				$meta->value        = $height;
				$meta->store();
			}
		}

		// After storing the photo, trigger rules that should occur after a photo is stored
		//$photo->afterStore( $file , $image );
		
		//$sphoto = new SocialPhotos($photo_obj->id);

		return $photo; 
	}
	
	public function crawlLink($urls)
	{
		// Get the crawler
		$crawler = FD::get('crawler');
		// Result placeholder
		$result = array();
		
		foreach ($urls as $url) {

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

			$result[$url] = json_decode($link->data);

		}
		
		return $result;
	}
	
	//function for upload file
	public function uploadFile()
	{
		$config = FD::config();
		$limit 	= $config->get( $type . '.attachments.maxsize' );

		// Set uploader options
		$options = array(
			'name'        => 'file',
			'maxsize' => $limit . 'M'
		);
		// Let's get the temporary uploader table.
		$uploader 			= FD::table( 'Uploader' );
		$uploader->user_id	= $this->plugin->get('user')->id;

		// Pass uploaded data to the uploader.
		$uploader->bindFile( $data );

		$state 	= $uploader->store();
	}
	
}
