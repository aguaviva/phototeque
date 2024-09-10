<?php
    $configs = include('config.php');
    $images_dir = $configs["images_dir"];
    $thumbs_dir = $configs["thumbs_dir"];

    function get_database() { return new SQLite3('test.db'); }

    function concat($array)
    {
        return "('".implode("','", $array)."')";
    }

    function split_tag_str($tag_list)
    {
        $out = array();
        foreach($tag_list as $tag) {
            $tag = trim($tag);
            if (strlen($tag)>0)
                array_push ($out, $tag);
        }

        return $out;
    }

    function add_images_tags($img_list, $tag_list)
    {
        $db = get_database();

        foreach ($tag_list as $tag) {
            $res = $db->exec("INSERT OR IGNORE INTO tags(name) VALUES('$tag')");
        }

        $str = "INSERT OR REPLACE INTO imgs_tags(img_id,tag_id) SELECT * FROM ".
                "(SELECT id from images where name in ". concat($img_list).") ".
                "CROSS JOIN (select id from tags where name in ".concat($tag_list).")";
        $res = $db->exec($str);
    }


    function del_images_tags($img_list, $tag_list)
    {
        $db = get_database();    
        $str = "DELETE FROM imgs_tags where (img_id, tag_id) in  SELECT * FROM".
               "(select id from images where name in ". concat($img_list).") ".
               "CROSS JOIN (select id from tags where name in ".concat($tag_list).")";
        $res = $db->exec($str);
    }

    function get_images($tag_list)
    {
        $tag_list = split_tag_str($tag_list);

        $db =get_database();

        if (count($tag_list)>0)
            $str = "SELECT * from images JOIN (select img_id FROM ((SELECT id FROM tags where name in ".concat($tag_list)." ) join imgs_tags  on id=tag_id) GROUP BY img_id HAVING COUNT(*) = ".count($tag_list)." ) on images.id=img_id;";
        else 
            $str = "SELECT * FROM images ORDER BY date desc";

        $results = $db->query($str);
        $out = array();
        while ($row = $results->fetchArray())
        {
            $out[basename($row["name"])] = array( "width"=> $row["width"], "height"=>$row["height"], "imgtWidth"=>$row["twidth"], "imgtHeight"=>$row["theight"], "date"=>$row["date"]);
        }

        return $out;
    }

    function get_tags()
    {
        $out = array();
        $db =get_database();
        $results = $db->query("SELECT name FROM tags");
        while ($row = $results->fetchArray())
        {
           array_push($out, $row["name"]);
        }
        return $out;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
        $query_string = $_SERVER['QUERY_STRING'];
        if(isset($query_string))
        {
            parse_str($query_string, $query_array);
            $command = $query_array["command"];
            if(isset($command))
            {
                header_remove(); 
                header('Content-Type: application/json; charset=utf-8');
                $data = json_decode(file_get_contents("php://input"), true);

                if($command=="add_tags")
                { // Check if form was submitted
                    add_images_tags($data["pics"], $data["tags"]);
                }
                else if($command=="del_tags")
                { // Check if form was submitted
                    del_images_tags($data["pics"], $data["tags"]);
                }
                else if($command=="get_images")
                {
                    print(json_encode(get_images($data["tags"])));
                }
                else if($command=="get_tags")
                {
                    print(json_encode(get_tags()));
                }
                else
                {
                    print($command);
                    return;
                }

                http_response_code(200);
                return;
            }
            else
            {
                print("unknown command '$command'");
                return;
            }
        }
        else
        {
            print("command not found");
            return;
        }
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1, maximum-scale=1">
        
        <!-- jQuery -->
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.3.1/dist/jquery.min.js" type="text/javascript"></script>
      
        <!-- nanogallery2 -->
        <link  href="https://cdn.jsdelivr.net/npm/nanogallery2@3/dist/css/nanogallery2.min.css" rel="stylesheet" type="text/css">
        <script  type="text/javascript" src="https://cdn.jsdelivr.net/npm/nanogallery2@3/dist/jquery.nanogallery2.min.js"></script>
        
        <!-- bootstrap -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-gH2yIJqKdNHPEq0n4Mqa/HGKIhSkIHeL5AyhkYV8i59U5AR6csBvApHHNl/vI1Bx" crossorigin="anonymous">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-A3rJD856KowSb7dwlZdYEkO39Gagi7vIsF0jrRAoQmDKKtQBHUuLZ9AsSv4jD4Xa" crossorigin="anonymous"></script>
    </head>
    <body>

    <script>
    'use strict';

<?php
    print("var images_dir = '$images_dir';\n");
    print("var thumbs_dir = '$thumbs_dir';\n");
?>
    var splitTest = function (str) { return str.split('\\').pop().split('/').pop(); }

    function group_by_date(items)
    {
        var groups = {}
        for (const [key, value] of Object.entries(items))
        {
            var label = value["date"].substr(0,7);
            groups[label] = groups[label] || {};
            groups[label][key]= value;
        }
        return groups;
    }

    function create_gallery(id, items)
    {
        $(id).nanogallery2( {thumbnailSelectable: true, thumbnailHoverEffect2: 'toolsAppear', thumbnailHeight: 200, thumbnailWidth: 'auto', items: items});
    }

    function create_all_galleries(image_list)
    {
        $("#gallery .ngy2_container").map(function(){return $(this).nanogallery2('destroy');});
        $("#gallery").empty();

        for (const [gallery_name, pic_list] of Object.entries(image_list))
        {
            $('#gallery').append(`<h1 style='text-align: center;'>${gallery_name}</h1><div id='${gallery_name}'>caca</div>\n`);

            var items = []
            for (const [pic, data] of Object.entries(pic_list))
            {
                 data["src"] = images_dir + pic;
                 data["srct"] = thumbs_dir + pic;
                 items.push(data);
            }

            create_gallery("#"+gallery_name, items);
        }
    }

    function post(command, myJSObject, cb_success)
    {
        $.ajax({
            type: "POST",
            url: "?command="+command,
            data: JSON.stringify(myJSObject),
            contentType : 'application/json',
            success: cb_success
        });
    }

    function get_images(tag_list, cb_success) { post("get_images", { "tags":tag_list }, cb_success); }
    function get_tags(cb_success) { post("get_tags", {}, cb_success); }

    function checkbox_tag(name, checked)
    {
        return '<div class="form-check">'+
                `<input class="form-check-input" type="checkbox" value="${name}" `  + (checked?'checked':'') + '/>' +
                `<label class="form-check-label" for="flexCheckDefault" >${name}</label>`+
                '</div>';
    }

    function create_checkbox_grid(tag_list, columns, list_selected_tags)
    {
        var str = "";
        for(var y=0;y<Math.ceil(tag_list.length/columns);y++)
        {
            str += '<div class="row">'
            for(var x=0;x<columns;x++)
            {
                var i = y*columns+x;
                var name = (i < tag_list.length) ? checkbox_tag(tag_list[i], list_selected_tags.includes(tag_list[i])) : "";
                str += `<div class="col">${name}</div>`;
            }
            str += '</div>'
        }
        return str;
    }

    function modal_show(modal, show) { modal.on('show.bs.modal', show); }
    function modal_hide(modal, hide) { modal.on('hide.bs.modal', hide); }

    function get_selected_images() { return $("#gallery .nGY2").map( function(k,v) { return $(v).nanogallery2('itemsSelectedGet').map(function(e) {return e.src}) } ) }
    function get_selected_tags(node) { return node.find('input:checked').map(function(){ return $(this).val();}).get(); }
    function get_tags_in_input(node) { return node.find("#tags").val().split(",").map(s => s.trim()); }

    jQuery(document).ready(function ()
    {
        var modal_filter = $("#ModalFilter")
        var tag_grid = modal_filter.find('.checkbox_tag_grid');

        var modal_tagger = $("#ModalTagger")
        var modal_select_by_tag = $("#ModalSelectByTag")

        get_images([], function(items) { create_all_galleries(group_by_date(items)) });

        modal_show(modal_filter, function()
        {
            var sel_grid = $(this).find('.selected_grid')
            sel_grid.empty().html(create_checkbox_grid(["selected"], 2, "")).find('input:checkbox').click(function()
            {
            })

            // tag grid
            get_tags(function(tag_list)
            {
                var selected_tags = get_selected_tags(tag_grid)

                tag_grid.empty().html(create_checkbox_grid(tag_list, 2, selected_tags)).find('input:checkbox').click(function()
                {
                    // recreate galeries
                    var selected_tags = get_selected_tags(tag_grid)
                    get_images(selected_tags, function(items)
                    {
                        create_all_galleries(group_by_date(items))
                    });
                })
            })
        });


        $("#TagSelectedBtn").on("click",function(){
            var selected_images = get_selected_images();
            if (selected_images.length==0)
            {
                alert("Select some images first");
            }
            else
            {
                modal_tagger.modal("show");
            }
        })

        modal_show(modal_tagger, function()
        {

            var grid = $(this).find('.checkbox_grid');

            get_tags(function(tag_list)
            {
                var selected_tags = get_selected_tags(tag_grid)

                grid.empty().html(create_checkbox_grid(tag_list, 2, selected_tags)).find('input:checkbox')
            })

        });

        modal_hide(modal_tagger, function()
        {
            var grid = $(this).find('.checkbox_grid');

            if ($(document.activeElement).attr('id') == "do_tag")
            {
                var selected_tags = get_selected_tags($(this));
                var new_tags = get_tags_in_input(modal_tagger);

                var added_tags = $(new_tags).not(selected_tags).get();
                var deleted_tags = $(selected_tags).not(new_tags).get();

                var selected_images = $.map(get_selected_images(), function(v) {return v.replace(/^.*[\\\/]/, '');})

                post("add_tags", {"pics":selected_images, "tags": added_tags}, function(result) { });
                post("del_tags", {"pics":selected_images, "tags": deleted_tags}, function(result) { });
            }
        });

        modal_show(modal_select_by_tag, function()
        {
            var grid = $(this).find('.checkbox_grid');

            get_tags(function(tag_list)
            {
                var selected_tags = get_selected_tags(tag_grid)

                grid.empty().html(create_checkbox_grid(tag_list, 2, selected_tags)).find('input:checkbox')
            })

        });

    });

    </script>


    <!-- bootstrap navbar -->
    <div id="ui">
        <nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Fixed navbar</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
            <ul class="navbar-nav me-auto mb-2 mb-md-0">
                <li class="nav-item">
                    <a class="nav-link active" id="TagSelectedBtn">Tag selected</a>
                </li>
                <li class="nav-item">
                <a data-bs-toggle="modal" data-bs-target="#ModalFilter" class="nav-link active">Filter</a>
                </li>
                <li class="nav-item">
                    <a data-bs-toggle="modal" data-bs-target="#ModalSelectByTag" class="nav-link active">Select by tag</a>
                </li>
                <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                Dropdown
            </a>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                <li><a class="dropdown-item" href="#">All</a></li>
                <li><a class="dropdown-item" href="#">None</a></li>
                <li><a class="dropdown-item" href="#">Range</a></li>
            </ul>
            </li>
            </ul>
            </div>
        </div>
        </nav>

        <!-- bootstrap tag dialogs -->

        <!-- Modal -->
        <div class="modal fade" id="ModalTagger" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">Set/Unset tags of selected items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="container checkbox_grid">
                </div>
                <label for="validationTooltip02" class="form-label">Type tags below for the selected images</label>
                <input class="form-control me-2" type="search" placeholder="tags..." aria-label="Search" id="tags">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="do_tag" data-bs-dismiss="modal">Tag</button>
            </div>
            </div>
        </div>
        </div> 

        <!-- Modal -->
        <div class="modal fade" id="ModalFilter" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">Filter by tag</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" >
                <div class="container selected_grid"></div>
                <div class="container checkbox_tag_grid"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
            </div>
        </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="ModalSelectByTag" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">Select by tag</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" >
                        <div class="container checkbox_grid">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div> 

    </div>    


    <h1 style='text-align: center;'>My Gallery</h1>

    <div id="gallery"></div>
<?php 
/*
    $images_dir = 'pics/';
    $thumbs_dir = "thumbs/";

    $database_filename = 'test.db';
    $db = new SQLite3($database_filename);

    $tag_list = array("delete"); 
    $results = $db->query("SELECT * FROM (imgs_tags JOIN images ON imgs_tags.img_id = images.id) where imgs_tags.tag_id in (select id from tags where tags.name in (".concat($tag_list)."));");

    //$results = $db->query("SELECT * FROM images ORDER BY date desc");

    $old_key="";
    while ($row = $results->fetchArray())
    {
        $date = $row["date"];
        $file = $row["name"];
        $width = $row["width"];
        $height = $row["height"];
        $twidth = $row["twidth"];
        $theight = $row["theight"];

        $key = date('Y-M',  strtotime($date));
        if ($old_key!=$key)
        {
            if ($old_key!="")
            {
                print("]);");
                print("</script>\n");
            }

            $old_key = $key;
            print("<h1 style='text-align: center;'>$key</h1>\n");

            $id = str_replace(' ', '_', $key);
            print("<div id='$id'></div>\n");
            print("<script>\n");

            print("create_gallery('#$id', './$images_dir', './$thumbs_dir', [\n");
        } 

        $file=basename($file);
        $thumb = "thumbs/".$file;
        print("{ src: '$file', width:'$width', height:'$height', imgtWidth:'$twidth', imgtHeight:'$theight'  },\n");
    }

    print("]);");
    print("</script>\n");
*/    
?>
      </body>
  </html>
          

