<?php
/**
 * Routines for generation of custom image sizes and deletion of these sizes.
 *
 * @since 1.9.0
 * @package themify
 */

if ( ! function_exists( 'themify_do_img' ) ) {
	/**
	 * Resize images dynamically using wp built in functions
	 *
	 * @param string|int $image Image URL or an attachment ID
	 * @param int $width
	 * @param int $height
	 * @param bool $crop
	 * @return array
	 */
	function themify_do_img( $image = null, $width, $height, $crop = false ) {
		$attachment_id = null;
		$img_url = null;

		$width = is_numeric( $width ) ? $width : '';
		$height = is_numeric( $height ) ? $height : '';
		// if an attachment ID has been sent
		if( is_int( $image ) ) {
			$post = get_post( $image );
			if( $post ) {
				$attachment_id = $post->ID;
				$img_url = wp_get_attachment_url( $attachment_id );
			}
			unset($post);
		} else {
			// URL has been passed to the function
			$img_url = esc_url( $image );

			// Check if the image is an attachment. If it's external return url, width and height.
			$upload_dir = themify_upload_dir();
			if(strpos($img_url,$upload_dir['baseurl'])===false){
				unset($upload_dir);
				if($width==='' || $height===''){
					$size=themify_get_image_size($img_url);
					if($size!==false){
						if($width===''){
							$width=$size['w'];
						}
						if($height===''){
							$height=$size['h'];
						}
					}
				}
				return array(
					'url' =>$img_url,
					'width' => $width,
					'height' => $height
				);
			}
			unset($upload_dir);
			// Finally, run a custom database query to get the attachment ID from the modified attachment URL
			$attachment_id = themify_get_attachment_id_from_url( $img_url);
		}
		// Fetch attachment meta data. Up to this point we know the attachment ID is valid.
		$meta = $attachment_id ?wp_get_attachment_metadata( $attachment_id ):null;

		// missing metadata. bail.
		if (!is_array( $meta ) ) {
			$ext=strtolower(strtok(pathinfo($img_url,PATHINFO_EXTENSION ),'?'));
			if(function_exists('getimagesize') && ($ext==='png' || $ext==='jpg' || $ext==='jpeg' || $ext==='webp' || $ext==='gif' ||$ext==='bmp' )){//popular types
				$upload_dir = themify_upload_dir();
				$attached_file=str_replace($upload_dir['baseurl'],$upload_dir['basedir'],$img_url);
				if(!is_file ($attached_file)){
					$attached_file=$attachment_id?get_attached_file( $attachment_id ):null;
				}
				if($attached_file){
					$size=getimagesize($attached_file);
					if($size){
						$meta=array(
						'width'=>$size[0],
						'height'=>$size[1],
						'file'=>trim(str_replace($upload_dir['basedir'],'',$attached_file),'/')
						);
					}
					unset($upload_dir,$ext,$size,$attached_file);
				}
			}
			if ( ! is_array( $meta ) ) {
				return array(
					'url' => $img_url,
					'width' => $width,
					'height' => $height
				);
			}
		}

		// Perform calculations when height or width = 0
		if( empty( $width ) ) {
			$width = 0;
		}
		if ( empty( $height ) ) {
			// If width and height or original image are available as metadata
			if ( !empty( $meta['width'] ) && !empty( $meta['height'] ) ) {
				// Divide width by original image aspect ratio to obtain projected height
				// The floor function is used so it returns an int and metadata can be written
				$height = floor( $width / ( $meta['width'] / $meta['height'] ) );
			} else {
				$height = 0;
			}
		}
		// Check if resized image already exists
		if ( is_array( $meta ) && isset( $meta['sizes']["resized-{$width}x{$height}"] ) ) {
			$size = $meta['sizes']["resized-{$width}x{$height}"];
			if( isset( $size['width'],$size['height'] )) {
				$split_url = explode( '/', $img_url );
				
				if( ! isset( $size['mime-type'] ) || $size['mime-type'] !== 'image/gif' ) {
					$split_url[ count( $split_url ) - 1 ] = $size['file'];
				}

				return array(
					'url' => implode( '/', $split_url ),
					'width' => $width,
					'height' => $height,
					'attachment_id' => $attachment_id
				);
			}
		}

		// Requested image size doesn't exists, so let's create one
		if ( true == $crop ) {
			add_filter( 'image_resize_dimensions', 'themify_img_resize_dimensions', 10, 5 );
		}
		// Patch meta because if we're here, there's a valid attachment ID for sure, but maybe the meta data is not ok.
		if ( empty( $meta ) ) {
			$meta['sizes'] = array( 'large' => array() );
		}
		// Generate image returning an array with image url, width and height. If image can't generated, original url, width and height are used.
		$image = themify_make_image_size( $attachment_id, $width, $height, $meta, $img_url );
		
		if ( true == $crop ) {
			remove_filter( 'image_resize_dimensions', 'themify_img_resize_dimensions', 10 );
		}
		$image['attachment_id'] = $attachment_id;
		return $image;
	}
}

if ( ! function_exists( 'themify_make_image_size' ) ) {
	/**
	 * Creates new image size.
	 *
	 * @uses get_attached_file()
	 * @uses image_make_intermediate_size()
	 * @uses wp_update_attachment_metadata()
	 * @uses get_post_meta()
	 * @uses update_post_meta()
	 *
	 * @param int $attachment_id
	 * @param int $width
	 * @param int $height
	 * @param array $meta
	 * @param string $img_url
	 *
	 * @return array
	 */
	function themify_make_image_size( $attachment_id, $width, $height, $meta, $img_url ) {
		if($width!==0 || $height!==0){
			setlocale( LC_CTYPE, get_locale() . '.UTF-8' );
			$upload_dir = themify_upload_dir();
			$attached_file=str_replace($upload_dir['baseurl'],$upload_dir['basedir'],$img_url);
			unset($upload_dir);
			if(!is_file ($attached_file)){
				$attached_file=get_attached_file( $attachment_id );
			}
			$source_size = apply_filters( 'themify_image_script_source_size', themify_get( 'setting-img_php_base_size', 'large', true ) );
			if ( $source_size !== 'full' && isset( $meta['sizes'][ $source_size ]['file'] ) ){
				$attached_file = str_replace( $meta['file'], trailingslashit( dirname( $meta['file'] ) ) . $meta['sizes'][ $source_size ]['file'], $attached_file );
			}
			unset($source_size);
			$resized = image_make_intermediate_size( $attached_file, $width, $height, true );
			if ( $resized && ! is_wp_error( $resized ) ) {

				// Save the new size in meta data
				$key = sprintf( 'resized-%dx%d', $width, $height );
				$meta['sizes'][$key] = $resized;
				$img_url = str_replace( basename( $img_url ), $resized['file'], $img_url );

				wp_update_attachment_metadata( $attachment_id, $meta );

				// Save size in backup sizes so it's deleted when original attachment is deleted.
				$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
				if ( ! is_array( $backup_sizes ) ){
					$backup_sizes = array();
				}
				$backup_sizes[$key] = $resized;
				update_post_meta( $attachment_id, '_wp_attachment_backup_sizes', $backup_sizes );
				$img_url=esc_url($img_url);
			}
		}
		// Return original image url, width and height.
		return array(
			'url' => $img_url,
			'width' => $width,
			'height' => $height
		);
	}
}
function themify_get_placeholder($url,$base=false){
	$upload_dir = themify_upload_dir();
	if(strpos($url,$upload_dir['baseurl'])===false){
		$size=themify_get_image_size($url);
		if($size===false){
			return false;
		}
		return array(
				's'=>"data:image/svg+xml;charset=UTF-8,".rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' width='{$size['w']}' height='{$size['h']}' fill='rgba(255,255,255,.2)'><rect width='100%' height='100%'/></svg>"),
				'w'=>$size['w'],
				'h'=>$size['h']
			);
	}
	$dir=str_replace($upload_dir['baseurl'],$upload_dir['basedir'],$url);
	if(!is_file ($dir)){
		return false;
	}
	$parts = pathinfo( $dir );
	$destination=rtrim($parts['dirname'],'/').'/'.$parts['filename'].'.svg';
	$result=str_replace($upload_dir['basedir'],$upload_dir['baseurl'],$destination);
	if(is_multisite() && strpos($upload_dir['basedir'],'blogs.dir',5)!==false){
		static $site_url=null;
		if($site_url===null){
			$site_url=rtrim(home_url(),'/').'/';
		}
		$result=str_replace(ABSPATH,$site_url,$destination);
	}
	if(!is_file($destination)){
		$ext=strtok($parts['extension'],'?');
		$parts=null;
		if($ext==='svg'){
			return $url;
		}
		if($ext==='png'){
			$im = imagecreatefrompng($dir);
		}
		elseif($ext==='jpg' || $ext==='jpeg'){
			$im = imagecreatefromjpeg($dir);
		}
		elseif($ext==='gif'){
			$im = imagecreatefromgif($dir);
		}
		else{
			$im = imagecreatefromstring(file_get_contents($dir));
		}
		if(!$im){
			return false;
		}
		$w=imagesx($im);
		$h=imagesy($im);
		$max_W=$w>=300?3:($w>100?4:5);
		$box_w=floor($w/$max_W);
		$max_H=$h>=300?3:($h>100?4:5);
		$box_h=floor($h/$max_H);
		$firstColor=null;
		$svg='';
		for($y=0;$y<$max_H;++$y){
			for($x=0;$x<$max_W;++$x){
				$x_corrd=$x*$box_w;
				$y_coord=$y*$box_h;
				$color=imagecolorsforindex($im, imagecolorat($im, ($x_corrd+$box_w)/2, ($y_coord+$box_h)/2));
				if ($color['red']>=256){
					$color['red']=240;
				}
				if ($color['green']>=256){
					$color['green']=240;
				}
				if ($color['blue']>=256){
					$color['blue']=240;
				}
				$color=substr('0'.dechex($color['red']),-2).substr('0'.dechex($color['green']),-2).substr('0'.dechex($color['blue']),-2);
				if($firstColor===null){
					$firstColor=$color;
				}
				$svg.='<rect width="'.$box_w.'" height="'.$box_h.'"';
				if($firstColor!==$color){
					$svg.=' fill="#'.$color.'"';
				}
				if($x_corrd>0){
					$svg.=' x="'.$x_corrd.'"';
				}
				if($y_coord>0){
					$svg.=' y="'.$y_coord.'"';
				}
				$svg.='/>';
			}
		}
		imagedestroy($im);
		$svg='<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'"><g fill="#'.$firstColor.'">'.$svg.'</g></svg>';
		if(!file_put_contents($destination,$svg)){
			return false;
		}
	}
	if($base===true){
		$tmp=file_get_contents($destination);
		if(!empty($tmp)){
			$result="data:image/svg+xml;charset=UTF-8,".rawurlencode($tmp);
		}
		unset($tmp);
	}
	if(!isset($w)){
		$s=themify_get_image_size($dir,true);
		if($s===false){
			$s=getimagesize($dir);
			$s=!empty($s)?array('w'=>$s[0],'h'=>$s[1]):array('w'=>'','h'=>'');
		}
	}
	else{
		$s=array('w'=>$w,'h'=>$h);
	}
	return array(
		's'=>$result,
		'w'=>$s['w'],
		'h'=>$s['h']
	);
}



/**
 * Disable the min commands to choose the minimum dimension, thus enabling image enlarging.
 *
 * @param $default
 * @param $orig_w
 * @param $orig_h
 * @param $dest_w
 * @param $dest_h
 * @return array
 */
function themify_img_resize_dimensions( $default, $orig_w, $orig_h, $dest_w, $dest_h ) {
	// set portion of the original image that we can size to $dest_w x $dest_h
	$aspect_ratio = $orig_w / $orig_h;
	$new_w = $dest_w;
	$new_h = $dest_h;

	if ( !$new_w ) {
		$new_w = (int)( $new_h * $aspect_ratio );
	}

	if ( !$new_h ) {
		$new_h = (int)( $new_w / $aspect_ratio );
	}

	$size_ratio = max( $new_w / $orig_w, $new_h / $orig_h );

	$crop_w = round( $new_w / $size_ratio );
	$crop_h = round( $new_h / $size_ratio );

	$s_x = floor( ( $orig_w - $crop_w ) / 2 );
	$s_y = floor( ( $orig_h - $crop_h ) / 2 );

	// the return array matches the parameters to imagecopyresampled()
	// int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h
	return array( 0, 0, (int) $s_x, (int) $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h );
}

if( ! function_exists( 'themify_get_attachment_id_from_url' ) ) :
	/**
	 * Get attachment ID for image from its url.
	 *
	 * @param string $url
	 * @param deprecated $base_url
	 * @return bool|null|string
	 */
	function themify_get_attachment_id_from_url( $url = '', $base_url = '' ) {
		// If this is the URL of an auto-generated thumbnail, get the URL of the original image
		$url = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif|webp)$)/i', '', $url );
		return !empty($url)?attachment_url_to_postid( $url ):null;
	}
endif;

/**
 * Removes protocol and www from URL and returns it
 *
 * @return string
 */
function themify_remove_protocol_from_url( $url ) {//deprecated will be removed
	return preg_replace( '/https?:\/\/(www\.)?/', '', $url );
}

function themify_createWebp($url){

	$res=$url;
	$info = pathinfo($res);
	if(!isset($info['extension'])){
	    return $url;
	}
	$orig_ex = strtok($info['extension'],'?');
	if($orig_ex==='webp' || $orig_ex==='apng' || $orig_ex==='bmp'){
	    return $url;
	}
	static $available=null;
	if($available===NULL){
		$available=array();
		if(apply_filters('themify_disable_webp',false)===false){
			if(class_exists('Imagick')){
				$im = new \Imagick();
				if (in_array('WEBP', $im->queryFormats('WEBP'),true)) {
					$available['Imagick']=true;
				}
				$im=null;
			}
			if(!isset($available['Imagick']) &&function_exists('imagewebp') && (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng'))){
				$available['GD']=true;
			}
		}
	}	
	if(!empty($available)){
		$upload_dir=  themify_upload_dir();
		$sameDomain=strpos($url,$upload_dir['baseurl'])!==false;
		if(is_multisite()){
			if($sameDomain===false){
				if(is_subdomain_install()){
					$blog_name = explode('.',$_SERVER['SERVER_NAME']);
					$blog_name=$blog_name[0];
					if(strpos($url,$blog_name)===false){
						return $url;
					}
				}
				else{
					if(!isset($_SERVER['SERVER_NAME']) || strpos($url,$_SERVER['SERVER_NAME'])===false){
						return $url;
					}
					static $site_url=null;
					if($site_url===null){
						$site_url = dirname(site_url());
					}
					if(strpos($url,$site_url)===false){
						return $url;
					}
					$blog_name =explode('/',trim(str_replace($site_url,'',$url),'/'));
					$blog_name=$blog_name[0];
				}
				static $sites=array();
				if(!isset($sites[$blog_name])){
					$blog = get_id_from_blogname($blog_name);
					if($blog===null){
						$sites[$blog_name]=false;
						return $url;
					}
					$currentBlog=pathinfo(get_site_url(),PATHINFO_FILENAME);
					switch_to_blog($blog );
				
					$blog_upload_dir_info = wp_get_upload_dir();	
					restore_current_blog();
					$sites[$blog_name] = array('basedir'=>$blog_upload_dir_info['basedir'],'baseurl'=>str_replace('/'.$currentBlog.'/','/'.$blog_name.'/',$blog_upload_dir_info['baseurl']));// bug in WP return the current blog name url,not switched
				}
				elseif($sites[$blog_name]===false){
					return $url;
				}
				$upload_dir=$sites[$blog_name];
			}
		}
		elseif($sameDomain===false){
			return $url;
		}
		$res=str_replace($upload_dir['baseurl'],$upload_dir['basedir'],$res);
		if(strpos($res,'http')!==false){
			return $url;
		}
		$resUrl=str_replace('.'.$orig_ex, '.webp', $res);
		if(is_file ($resUrl)){
			return str_replace($upload_dir['basedir'],$upload_dir['baseurl'],$resUrl);
		}
	    if(!is_file ($res)){
			return $url;
	    }   
		$lowerExt = strtolower($orig_ex);
	    if(isset($available['Imagick'])){
		    $im = new Imagick($res);
		    $im->setImageFormat( 'WEBP' );
		    $im->setOption('webp:method', '5'); 
			$im->setOption('webp:lossless','false');
			$im->setOption('webp:low-memory', 'true');
			if($lowerExt==='png'){
				$im->setOption('webp:alpha-compression', 1);
				$im->setOption('webp:alpha-quality', 85);
			}
			$im->stripImage();
		    $im->writeImage($resUrl); 
			$imageBlob = $im->getImageBlob();
			$webp = file_put_contents($resUrl, $imageBlob);
			if($webp){
				$res=$resUrl;
			}
	    }
	    elseif(isset($available['GD'])){
			if($lowerExt==='png' && version_compare(PHP_VERSION, '7.0.0', '<')){
				$hasTransparent=false;
				if(isset($available['Imagick']) || class_exists('Imagick')){
					$image = new Imagick($res);
					if(method_exists($image,'getImageAlphaChannel')){
						$hasTransparent=$image->getImageAlphaChannel()===1?true:null;
					}
				}
				if($hasTransparent!==true){
					if($hasTransparent===false){
						if(ord ( file_get_contents( $res, false, null, 25, 1 ) ) & 4){
						$hasTransparent=true;
						}
						else{
						$contents = file_get_contents( $res );
						if ( stripos( $contents, 'PLTE' ) !== false && stripos( $contents, 'tRNS' ) !== false ){
							$hasTransparent=true;
						}
						}
					}
					elseif($hasTransparent===null){
						$hasTransparent=false;
					}
				}
				if($hasTransparent===true){
					return $url;
				}
			}
			
			switch($lowerExt){
				case 'jpeg':
				case 'jpg':
					if(!function_exists('imagecreatefromjpeg')){
						return $url;
					}
					$im = imagecreatefromjpeg($res);
					break;
				case 'png':
					if(!function_exists('imageCreateFromPng')){
						return $url;
					}
					$pngimg  = imageCreateFromPng($res);
					// get dimens of image
					$w = imagesx($pngimg );
					$h = imagesy($pngimg );
					$im = imagecreatetruecolor ($w, $h);
					imageAlphaBlending($im, false);
					imageSaveAlpha($im, true);
					// By default, the canvas is black, so make it transparent
					$trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
					imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $trans);
					// copy png to canvas
					imagecopy($im, $pngimg , 0, 0, 0, 0, $w, $h);
					imagedestroy($pngimg);
					break;
				case 'gif':
					if(!function_exists('imageCreateFromGif')){
						return $url;
					}
					$im = imageCreateFromGif($res);
					break;
				default:
				return $url;
			}

			if(empty($im)){
				return $url;
			}
			$res=$resUrl;
			$webp =imagewebp($im, $res);
			if($webp){
				if (filesize($res) % 2 === 1) {//The following hack solves an `imagewebp` bug
					file_put_contents($res, "\0", FILE_APPEND);
				}
			}
			else{
				unlink($res);
			}
			imagedestroy($im);
	    }
		else{
			return $url;
		}
	    return $webp?str_replace($upload_dir['basedir'],$upload_dir['baseurl'],$res):$url;
	}
	else{
	    return $url;
	}
}
