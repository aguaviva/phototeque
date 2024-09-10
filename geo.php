<!DOCTYPE html>
<html lang="en">
<head>
    <base target="_top">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Custom Icons Tutorial - Leaflet</title>

    <link rel="shortcut icon" type="image/x-icon" href="docs/images/favicon.ico" />

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster-src.js" integrity="sha384-N9K+COcUk7tr9O2uHZVp6jl7ueGhWsT+LUKUhd/VpA0svQrQMGArhY8r/u/Pkwih" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />

    <script src="libs/Leaflet.Photo.js"></script>
    <link rel="stylesheet" href="libs/Leaflet.Photo.css" />

    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        .leaflet-container {
            height: 100%;
            width: 100%;
            max-width: 100%;
            max-height: 100%;
        }
        .leaflet-popup-content {
            width: ;
        }
    </style>
</head>
<body onload="onLoad()">
    <script>
        <?php
        $configs = include('config.php');
        $images_dir = $configs["images_dir"];
        $thumbs_dir = $configs["thumbs_dir"];

        $database_filename = 'test.db';
        $db = new SQLite3($database_filename);

        $str = "SELECT * FROM images";
        $results = $db->query($str);
        $out = array();
        print("var photos = [");
        while ($row = $results->fetchArray())
        {
            $url = basename($row["name"]);
            $lat = substr($row["lat"], 0,-2);
            $lon = substr($row["lon"], 0,-2);
            $twidth = $row["twidth"]/2;
            $theight = $row["theight"]/2;
            if (strlen($lat)>2)
            {
                //print("add_icon('$thumbs_dir$url', $lat, $lon, [$twidth, $theight]);\n");
                print("['$url', $lat, $lon],");
            }
        }
        print("];\n");
        ?>

        function add_photo(url, lat, lon)
        {
            return {
                lat: lat,
                lng: lon,
                url: "<?php echo $images_dir; ?>"+url,
                caption: '<a href="<?php echo $images_dir; ?>' + url +'">'+url+'</a>',
                thumbnail: "<?php echo $thumbs_dir; ?>" + url,
                video: null
            };
        }

        function onLoad()
        {
            var map = new L.map('map');

            //let layer = new L.TileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
            var layer = L.tileLayer('http://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png');
            map.addLayer(layer);

            var photoLayer = L.photo.cluster().on('click', function (evt) {
                var photo = evt.layer.photo,
                    template = '<a href="{url}" target="_blank"> <img src="{thumbnail}" /></a>';

                if (photo.video && (!!document.createElement('video').canPlayType('video/mp4; codecs=avc1.42E01E,mp4a.40.2'))) {
                    template = '<video autoplay controls poster="{url}"><source src="{video}" type="video/mp4"/></video>';
                };

                evt.layer.bindPopup(L.Util.template(template, photo), {
                    className: 'leaflet-popup-photo',
                    //minWidth: 400
                    maxWidth: 400
                    //minHeight:400
                }).openPopup();
            });

            var photosMarker = photos.map(function(args) {
                return add_photo.apply(this, args);
            });
            photoLayer.add(photosMarker).addTo(map);

            map.fitBounds(photoLayer.getBounds());
        }
    </script>
    <div id='map'></div>
</body>
</html>