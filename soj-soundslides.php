<?php
/*
Plugin Name: SoJ SoundSlides
Plugin URI: http://journalism.indiana.edu/apps/mediawiki-1.10.1/index.php/Wp_soj-soundslides
Description: Upload a SoundSlides presentation to the blog.
Version: 1.2.2
Author: Jeff Johnson
*/

	/**
	 * Function to list directory contents. Returns an associative array of directory
	 * and file names, where directories have as sub-arrays the files they contain
	 *
	 * @param $dir The name of the directory you're opening; must be absolute.
	 * @package utilities
	 */
	function soj_soundslides_get_directory_listing($dir)
	{
		$results = array();

		// If it's not a directory, return an empty array
		if(!is_dir($dir)) return $results;
	
		$dh = opendir($dir);
	
		while($file=readdir($dh))
		{
			$full_path = $dir.'/'.$file;

			// Don't include files whose name begins with "."
			$pos = strpos($file,'.');
			if(!is_dir($full_path) && $pos!==FALSE && $pos==0)
				continue;

			// Exclude parents and selves from the results
			if($file!='..' && $file!='.')
			{
				// If it's a sub-directory, make recursive call
				if(is_dir($full_path))
					$results[$file] = soj_soundslides_get_directory_listing($dir.'/'.$file);
				else
					$results[$file] = $full_path;
			}			
		}
	
		closedir($dh);
		return $results;
	}

	/**
	 * Function to remove directories, even if they contain files or
	 * subdirectories.  Returns array of removed/deleted items, or false if
	 * nothing was removed/deleted.
	 *
	 * by Justin Frim.  2007-01-18
	 */
	function soj_soundslides_rmdirtree($dirname)
	{
		// Operate on dirs only
		if(is_dir($dirname))
		{
			$result=array();
			
			// Append slash if necessary
			if(substr($dirname,-1)!='/') $dirname.='/';

			$handle = opendir($dirname);
			while(false !== ($file = readdir($handle)))
			{
				// Ignore . and ..
				if($file!='.' && $file!= '..')
				{
					$path = $dirname.$file;

					// Recurse if subdir, Delete if file
					if(is_dir($path))
					{
						$result = array_merge($result,soj_soundslides_rmdirtree($path));
					}
					else
					{
						unlink($path);
						$result[] .= $path;
					}
				}
			}
			closedir($handle);
			
			// Remove dir
			rmdir($dirname);
			
			$result[] .= $dirname;
			
			// Return array of deleted items
			return $result;
		}
		else
		{
			// Return false if attempting to operate on a file
			return false;
		}
	}

	/** 
	 * Determine images directory
	 */
	$web_base_dir = get_option('web_base_dir');
	$base_dir = $_SERVER['DOCUMENT_ROOT'].$web_base_dir;
	$site_dir = $base_dir;
	$server_dir = $site_dir;
	$web_dir = $web_base_dir;
	$soundslides_server_dir = $server_dir.'SoundSlides/';
	$soundslides_web_dir = $web_dir.'SoundSlides/';

	DEFINE('SOJ_SOUNDSLIDES_DEFAULT_WIDTH', '600');
	DEFINE('SOJ_SOUNDSLIDES_DEFAULT_HEIGHT', '500');

	/**
	 * Generate admin panel for plugin
	 */
	function soj_soundslides_options_subpanel()
	{
	
		if ( function_exists('wp_nonce_field') )
			wp_nonce_field('plugin-name-action_' . $your_object);

		global $web_base_dir;
		global $base_dir;
		global $site_dir;
		global $server_dir;
		global $web_dir;
		global $soundslides_server_dir;
		global $soundslides_web_dir;

		// Handle setting settings
		if(isset($_POST['action']))
		{

			switch($_POST['action'])
			{
				case 'reset':
					delete_option('web_base_dir');
					$message = 'Reset successful.';
					break;

				case 'uploadDirectory':
					if(!get_option('web_base_dir'))
					{
						add_option('web_base_dir',$_POST['uploadDirectory']);
						$message = 'Updated succesfully.';
						
					}
					else
					{
						update_option('web_base_dir',$_POST['uploadDirectory']);
						$message = 'Updated succesfully.';
					}
					
					if(!isset($message))
						$message = 'Problem updating.';
					break;

				case 'updateSoJSoundslide':

					// Make sure this is set
					if(!get_option('web_base_dir'))
						add_option('web_base_dir',$_POST['uploadDirectory']);

					// Make sure you have a name, publish-to-web folder and a player
					if( strcmp($_FILES['soj-soundslide_ptw_zip']['name'],'')==0
						|| strcmp($_POST['soj-soundslide_presentation_name'],'')==0)
					{
						$message = 'Make sure to fill out all fields.';
						break;
					}

					// Make presentation name web-safe
					$_POST['soj-soundslide_presentation_name'] = preg_replace('/[^a-zA-Z0-9]+/','_',$_POST['soj-soundslide_presentation_name']);

					// Make sure the ZIP file is a zip
					if(strcmp(strtolower(substr(strrchr($_FILES['soj-soundslide_ptw_zip']['name'], '.'), 1)),'zip')!=0)
					{
						$message = 'Make sure that your \'Publish to Web folder\' is a ZIP file and that your SWF is an swf file.';
						break;
					}
	
					// Make sure upload directory exists
					if(!is_dir($base_dir))
						mkdir($base_dir);
					if(!is_dir($site_dir))
						mkdir($site_dir);
					if(!is_dir($server_dir))
						mkdir($server_dir);
					if(!is_dir($soundslides_server_dir))
						mkdir($soundslides_server_dir);

					// Create directory for this project
					if(!is_dir($soundslides_server_dir.$_POST['soj-soundslide_presentation_name']))
					{
						$presentation_dir = $soundslides_server_dir.$_POST['soj-soundslide_presentation_name'].'/';
						mkdir($presentation_dir);
					}
					else
					{
						$message = 'There is already a project by this name. Please use a different name.';
						break;
					}
	
					// Only proceed if a file was uploaded
					$ptw_upload_filename = trim($_FILES['soj-soundslide_ptw_zip']['name']);

					if(strcmp($ptw_upload_filename,'')!=0)
					{
						$filename = basename($ptw_upload_filename);
						$ext = strtolower(substr(strrchr($filename, '.'), 1));
						$basename = substr($filename,0,strrpos($filename,'.'));
						$target_path = $presentation_dir.$filename;
						$cnt = 1;
						while(file_exists($target_path))
						{
							$filename = $basename.'('.($cnt++).').'.$ext;
							$target_path = $presentation_dir.$filename;
						}
		
						if(move_uploaded_file($_FILES['soj-soundslide_ptw_zip']['tmp_name'], $target_path))
						{
							// Unzip folder
							$zip = new ZipArchive;
							if($zip->open($target_path)===TRUE)
							{
								$zip->extractTo($presentation_dir);
								$zip->close();
							}
							else
							{
								$message = '\'Publish to Web folder\' extraction failed. Make sure the file is not corrupt.';
								break;
							}
							
							// Delete ZIP file
							unlink($target_path);
						}
						else
						{
							$message = '\'Publish to Web folder\' upload failed. Make sure you have proper permissions set.';
							break;
						}
					}
					
					$message = 'Upload successful!';
	
					break;
				
				case 'deleteSoJSoundslide':

					// Get a list of all presentations you're removing
					foreach($_POST as $key=>$value)
					{
						$errors = array();
						$len = strlen($key);
						$pos = strpos($key,'soundslide');
						$display_message = FALSE;
						if($pos===0 && $len>10)
						{
							$presentation = substr($key,10);
							if(!soj_soundslides_rmdirtree($soundslides_server_dir.$presentation))
								$errors[] = $presentation;
							else
								$display_message = TRUE;
						}
					}

					// Generate message
					if(count($errors)>0)
						$message = 'There was a problem deleting the following presentations: '.implode(', ',$errors);
					elseif($display_message)
						$message = 'Deletion successful.';

					break;

				default:
					break;
			}

			if(isset($message))
			{
			?>
			<div id="message" class="updated fade">
				<p><?php _e($message); ?></p>
			</div>
			<?php
			}

		}
?>

	<div class="wrap"> 
		<h2><?php _e('How to use the SoJ Soundslides plugin') ?></h2>
		<p>To upload a presentation:</p>
		<ol>
			<li>Create a ZIP archive of the files in your publish folder</li>
			<li>Use the "Upload archive" option to upload the ZIP</li>
			<li>Give the presentation a name</li>
			<li>Click 'Save'</li>
		</ol>
		<p>To use a presentation:</p>
		<ol>
			<li>Copy either the short form HTML (&lt;sojsoundslide&gt;...&lt;/sojsoundslide&gt;) or the full HTML (in the text box) from the list of existing presentations (the list will not appear if there are none)</li>
			<li>Open the post or page in which you want the presentation to appear in the editor</li>
			<li>Switch to 'Source' view</li>
			<li>Paste the HTML where you would like the presentation to appear</li>
			<li>Save</li>
			<li>The presentation will now appear when the page is viewed</li>
		</ol>
		<p>&nbsp;</p>

		<h3>Upload directory</h3>
		<form method="post" action="" enctype="multipart/form-data">
	  <fieldset class="options">
	  	<legend>This is where the files will be stored... you probably don't need to change this</legend>
	  	<?php
	  		if($upload_dir=get_option('web_base_dir'));
	  		else
	  		{
				$tmp = substr($_SERVER['SCRIPT_FILENAME'],strlen($_SERVER['DOCUMENT_ROOT']));
				$pieces = explode('/',$tmp);
				unset($pieces[count($pieces)-1]);
				unset($pieces[count($pieces)-1]);
				$upload_dir = implode('/',$pieces).'/wp-content/uploads/';
				add_option('web_base_dir',$upload_dir);
	  		}
	  	?>
	  	<input type="text" name="uploadDirectory" size="95" value="<?php echo $upload_dir; ?>" />
	  	<input type="submit" value="Submit" />
	  	<input type="hidden" name="action" value="uploadDirectory" />
	  </fieldset>
	   </form>
	   
	   <h3>New presentation</h3>

		<form method="post" action="" enctype="multipart/form-data">
	  <fieldset class="options">
		<legend>This is where you upload new presentations</legend>
		<table cellpadding="3" cellspacing="0">
		<tbody>
		<tr>
		<td><label>Upload archive:</label></td>
		<td><input type="file" name="soj-soundslide_ptw_zip" /></td>
		</tr>
		<tr>
		<td><label>Presentation name:</label></td>
		<td><input type="text" size="35" name="soj-soundslide_presentation_name" /></td>
		</tr>
		</tbody>
		</table>
		</fieldset>
		<div class="submit">
		  <input type="hidden" name="action" value="updateSoJSoundslide" />
		  <input type="submit" name="info_update" value="<?php _e('Save', 'soj-soundslides'); ?> &#187;" />
		</div>
		</form>
		<?php
			// Get listing of existing directories (if any exist)
			$presentations = soj_soundslides_get_directory_listing($soundslides_server_dir);
			if(count($presentations)>0)
			{
		?>
		
		<h3>Existing presentations</h3>
		<form method="post" action="" enctype="multipart/form-data">
		<fieldset class="options">
		<legend>After a new presentation is uploaded, it appears here</legend>
		<ol>
		<?php
			foreach($presentations as $key=>$value)
			{
				echo '<li>';
				echo '<input type="checkbox" name="soundslide'.$key.'" /> &lt;sojsoundslide width="'.SOJ_SOUNDSLIDES_DEFAULT_WIDTH.'" height="'.SOJ_SOUNDSLIDES_DEFAULT_HEIGHT.'"&gt;<strong>'.$key.'</strong>&lt;/sojsoundslide&gt;';
				echo '<br />';
				echo '<textarea rows="6" cols="35">';
				$tmp = '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,40,0" width="'.SOJ_SOUNDSLIDES_DEFAULT_WIDTH.'" height="'.SOJ_SOUNDSLIDES_DEFAULT_HEIGHT.'">
<param name="allowScriptAccess" value="sameDomain">';
$player_path = $soundslides_web_dir.$key.'/';
$player = is_file($_SERVER['DOCUMENT_ROOT'].$player_path."soundslider.swf") ? $player_path."soundslider.swf" : $player_path."player.swf";
$tmp .= '<param name="movie" value="'.$player.'?size=1">
<param name="scale" value="showall">
<param name="quality" value="high">
<param name="allowFullScreen" value="true">
<embed src="'.$player.'?size=1" quality="high" width="'.SOJ_SOUNDSLIDES_DEFAULT_WIDTH.'" height="'.SOJ_SOUNDSLIDES_DEFAULT_HEIGHT.'" type="application/x-shockwave-flash" pluginspace="http://www.macromedia.com/go/getflashplayer" scale="showall" allowFullScreen="true"></embed>
</object>';
				echo htmlspecialchars($tmp,ENT_QUOTES);
				echo '</textarea>';
				echo '</li>';
			}
		?>
		</ol>
		</fieldset>
		<div class="submit">
		  <input type="hidden" name="action" value="deleteSoJSoundslide" />
		  <input type="submit" name="info_delete" value="<?php _e('Delete checked', 'soj-soundslides'); ?> &#187;" />
		</div>
		</form>
		<?php } ?>
		<div style="clear:both;"></div>
		<form method="post" action="" enctype="multipart/form-data">
	  <fieldset class="options">
	  	<legend>Reset plugin:</legend>
	  	<input type="submit" value="Reset" />
	  	<input type="hidden" name="action" value="reset" />
	  </fieldset>
	   </form>
	</div>
<?php
	}

	/**
	 * Embed slideshow code into content
	 */
	function soj_soundslides_content($text)
	{
		global $soundslides_web_dir;

		return preg_replace_callback(
			'|<sojsoundslide([^<]*)>(.*)</sojsoundslide>|',
			create_function(
				// single quotes are essential here,
				// or alternative escape all $ as \$
				'$matches',
				'$sub_matches = array();
preg_match("/width=\"([0-9]*)\"/",$matches[1],$sub_matches);
$width = is_numeric($sub_matches[1]) ? $sub_matches[1] : '.SOJ_SOUNDSLIDES_DEFAULT_WIDTH.';
$sub_matches = array();
preg_match("/height=\"([0-9]*)\"/",$matches[1],$sub_matches);
$height = is_numeric($sub_matches[1]) ? $sub_matches[1] : '.SOJ_SOUNDSLIDES_DEFAULT_HEIGHT.';

$player_path = "'.$soundslides_web_dir.'".$matches[2]."/";
$player = is_file($_SERVER["DOCUMENT_ROOT"].$player_path."soundslider.swf") ? $player_path."soundslider.swf" : $player_path."player.swf";
return "<div class=\"sojSoundSlideContainer\" style=\"width:".$width."px;height:".$height."px;\"><object classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" codebase=\"http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,40,0\" width=\"".$width."\" height=\"".$height."\">
<param name=\"allowScriptAccess\" value=\"sameDomain\">
<param name=\"movie\" value=\"".$player."\">
<param name=\"scale\" value=\"showall\">
<param name=\"quality\" value=\"high\">
<param name=\"allowFullScreen\" value=\"true\">
<embed src=\"".$player."\" quality=\"high\" width=\"".$width."\" height=\"".$height."\" type=\"application/x-shockwave-flash\" pluginspace=\"http://www.macromedia.com/go/getflashplayer\" scale=\"showall\" allowFullScreen=\"true\"></embed>
</object></div>";
'
			),
			$text
		);
	}
	add_filter('the_content', 'soj_soundslides_content');

	/**
	 * Add admin panel for plugin
	 */
	function soj_soundslides_panel()
	{
		if (function_exists('add_options_page')) {
			add_options_page('SoJ SoundSlides', 'SoJ SoundSlides', 'edit_posts', __FILE__, 'soj_soundslides_options_subpanel');
		}
	 }
	 add_action('admin_menu', 'soj_soundslides_panel');

	/**
	 * Add styles to hide the soundslide
	 */
	function soj_soundslides_hider()
	{
		echo '
<style type="text/css">
#sojSoundSlideContainer { margin: 0 -9px }
</style>
';
	}
	add_action('wp_head','soj_soundslides_hider');
?>