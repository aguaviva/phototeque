<?php
function format_gps_data($gpsdata,$lat_lon_ref)
{
    $gps_info = array();
    foreach($gpsdata as $gps)
    {
        list($j , $k) = explode('/', $gps);
        array_push($gps_info,$j/$k);
    }
    $coordination = $gps_info[0] + ($gps_info[1]/60.00) + ($gps_info[2]/3600.00);
    return (($lat_lon_ref == "S" || $lat_lon_ref == "W" ) ? '-'.$coordination : $coordination).' '.$lat_lon_ref;
}

function get_gps($exif_gps)
{
    $lat = 0;
    $lon = 0;
    if (array_key_exists("GPS", $exif_gps))
    {
        $details = $exif_gps["GPS"];
        if (array_key_exists("GPSLatitude", $details) && array_key_exists("GPSLongitude", $details))
        {
            $lat = format_gps_data($details['GPSLatitude'],$details['GPSLatitudeRef']);
            $lon = format_gps_data($details['GPSLongitude'],$details['GPSLongitudeRef']);
        }
    }
    return array($lat, $lon);
}

function get_orientation($exif) {
    $angle = 0;
    if (!empty($exif['Orientation'])) {
        switch ($exif['Orientation']) {
        case 3:
            $angle = 180 ;
            break;

        case 6:
            $angle = 270;
            break;

        case 8:
            $angle = 90;
            break;
        default:
            $angle = 0;
            break;
        }
    }
    return $angle;
}

function get_image_data($img_file)
{
    $exif_array = exif_read_data($img_file,  null, true);
    if ($exif_array==false)
    {
        print("error reading $img_file data\n");
        return false;
    }
    //var_dump($exif_array);

    if (array_key_exists("COMPUTED", $exif_array))
    {
        $computed = $exif_array['COMPUTED'];
        $width = $computed["Width"];
        $height = $computed["Height"];
    }

    $date = date("Y-m-d H:i:s");
    if (array_key_exists("FILE", $exif_array))
    {
        $file = $exif_array['FILE'];
        //var_dump($file["FileDateTime"]);
        $date = date('Y-m-d H:i:s', $file["FileDateTime"]);
    }

    if ($date==NULL && array_key_exists("EXIF", $exif_array))
    {
        $exif = $exif_array['EXIF'];
        //var_dump($exif["DateTimeOriginal"]);
        $date = date('Y-m-d H:i:s', strtotime($exif["DateTimeOriginal"]));

    }

    #is it a whatsapp image? Then take date from name
    if (preg_match("/^IMG-([0-9]{4})([0-9]{2})([0-9]{2})-WA/", basename($img_file), $out))
    {
        $date = $out[1]."-".$out[2]."-".$out[3]." 00:00:00";
    }

    //IFD0

    $angle = 0;
    if (array_key_exists("IFD0", $exif_array))
    {
        $idf0 = $exif_array['IFD0'];
        $angle = get_orientation($idf0);
    }

    // GPS

    $gps = get_gps($exif_array);
    $lat = $gps[0];
    $lon = $gps[1];

    return array('date' => $date,
                 'angle' => $angle,
                 'width' => $width,
                 'height' => $height,
                 'lat' => $lat,
                 'lon' => $lon);
}

function imagecreatefromjpegexif($filename)
{
    $img = imagecreatefromjpeg($filename);
    $exif = exif_read_data($filename);
    if ($exif==false)
        return false;
    if ($img && $exif && isset($exif['Orientation']))
    {
        $ort = $exif['Orientation'];

        if ($ort == 6 || $ort == 5)
            $img = imagerotate($img, 270, null);
        if ($ort == 3 || $ort == 4)
            $img = imagerotate($img, 180, null);
        if ($ort == 8 || $ort == 7)
            $img = imagerotate($img, 90, null);

        if ($ort == 5 || $ort == 4 || $ort == 7)
            imageflip($img, IMG_FLIP_HORIZONTAL);
    }
    return $img;
}

function make_thumb($src, $dest, $desired_height) {
    /* read the source image */
    if (filesize($src)==0)
        return false;

    $source_image = imagecreatefromjpegexif($src);
    if ($source_image==false)
        return false;

    //$source_image = imagerotate($source_image, $angle, 0);

    $width = imagesx($source_image);
    $height = imagesy($source_image);

    /* find the “desired height” of this thumbnail, relative to the desired width  */
    $desired_width = floor(($width * $desired_height) / $height);

    /* create a new, “virtual” image */
    $virtual_image = imagecreatetruecolor($desired_width, $desired_height);

    /* copy source image at a resized size */
    imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);

    /* create the physical thumbnail image to its destination */
    imagejpeg($virtual_image, $dest);
    return true;
}

function get_list_of_images_in_folder($images_dir)
{
    $glob_files = glob($images_dir.'*.{jpg,jpeg}', GLOB_BRACE);
    $glob_files = array_map('basename', $glob_files);
    return $glob_files;
}

function get_registered_files($db)
{
    $known_files = array();
    $qResult = $db->query("SELECT name FROM images");
    while ($row = $qResult->fetchArray())
    {
        array_push($known_files, $row["name"]);
    }
    return $known_files;
}

function sort_files_by_time($glob_files)
{
    $file_mtimes = array();
    foreach($glob_files as $file)
    {
        $file_mtimes[$file] = filemtime($images_dir.$file);
    }
    usort($glob_files, function($a, $b) {
        global $file_mtimes;
        return $file_mtimes[$b] - $file_mtimes[$a];
    });
    return $glob_files;
}

function find_new_files($db, $images_dir)
{
    $glob_files = get_list_of_images_in_folder($images_dir);
    $known_files = get_registered_files($db);

    # get new files
    $new_files = array();
    foreach($glob_files as $file)
    {
        if (array_search($file, $known_files) === false)
        {
            array_push($new_files, $file);
        }
    }
    return $new_files;
}

function register_image($db, $img_file)
{
    if (filesize($img_file)==0)
        return false;

    $img_data = get_image_data($img_file);
    if ($img_data==false)
        return false;

    $date = $img_data["date"];
    $angle = $img_data["angle"];
    $width = $img_data["width"];
    $height= $img_data["height"];
    $lat = $img_data["lat"];
    $lon = $img_data["lon"];

    // compute tumbs size
    $desired_height = 200;

    $twidth = $width;
    $theight = $height;
    if ($angle==90 || $angle==270)
    {
        $twidth = $height;
        $theight = $width;
    }

    $twidth = floor(($twidth * $desired_height) / $theight);
    $theight = $desired_height;

    $basefile = basename($img_file);
    $str = "INSERT INTO images(name, date, width, height, twidth, theight, lat, lon) VALUES('$basefile', '$date', $width, $height, $twidth, $theight, '$lat', '$lon')";
    print("adding: '$basefile', '$date', $width, $height, $twidth, $theight, '$lat', '$lon'");
    $res = $db->exec($str);
    print(" res: '$res' <br/>\n"   );
    return true;
}

function register_new_images($db, $images_dir)
{
    $db_tasks = tasks_get_db();
    $current_tasks = tasks_get_all_tasks_names($db_tasks);

    $new_files = find_new_files($db, $images_dir);
    foreach($new_files as $file)
    {
        if (register_image($db, $images_dir.$file))
        {
            // queue for generating thumbnail
            if (in_array($file, $current_tasks)==false)
            {
                tasks_push_back($db_tasks, $file);
            }
        }
    }

    $db_tasks->close();
}

function generate_thumbnails_from_tasks($images_dir, $thumbs_dir)
{
    $db_tasks = tasks_get_db();
    $thumb_names = tasks_get_all_tasks_names($db_tasks);
    print("\nCreating thumbnails\n");
    foreach($thumb_names as $file)
    {
        $img_file = $images_dir.$file;
        $thumbnail_file = $thumbs_dir.$file;
        print("'$thumbnail_file'\n");

        if (file_exists($thumbnail_file)==false)
        {
            $desired_height = 200;
            if (make_thumb($img_file, $thumbnail_file, $desired_height)==false)
            {
                print("error creating thumb\n");
            }
        }
        else
        {
            print("thumb exist\n");
        }
        tasks_delete($db_tasks, $file);
    }
    print("done\n");
}

header("Content-Type: text/plain");

$fp = fopen('/tmp/php-commit.lock', 'c');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    print("flock!");
    exit;
}

$configs = include('config.php');
$images_dir = $configs["images_dir"];
$thumbs_dir = $configs["thumbs_dir"];

# open database & crate tables
$database_filename = 'test.db';
$db = new SQLite3($database_filename);
$db->exec("CREATE TABLE IF NOT EXISTS images(id INTEGER PRIMARY KEY, name TEXT UNIQUE, date TEXT, width INT, height INT, twidth INT, theight INT, lat TEXT, lon TEXT)");
$db->exec("CREATE TABLE IF NOT EXISTS tags(id INTEGER PRIMARY KEY, name TEXT UNIQUE)");
$db->exec("CREATE TABLE IF NOT EXISTS imgs_tags(img_id INTEGER, tag_id INTEGER, UNIQUE(img_id, tag_id))");


include 'task.php';

register_new_images($db, $images_dir);    //die();
generate_thumbnails_from_tasks($images_dir, $thumbs_dir);

//$db->exec("END TRANSACTION");
$db_tasks->close();
$db->close();

flock($fp, LOCK_UN);
fclose($fp);
?>