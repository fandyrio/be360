<html>
    <head>

    </head>
    <body>
        <h5><center>Monitor</center></h5>
        <div style="width:100%;float:left">
            <select class="zonasi">
                <option value=""></option>
                @for($x=0;$x<$jumlah;$x++)
                    <option value="{{ $zonasi[$x]['enc_id'] }}">{{ $zonasi[$x]['nama_zona'] }}</option>
                @endfor
            </select>
        </div>
        <div class="content-double">
            
        </div>
    </body>
</html>
<script src="https://code.jquery.com/jquery-4.0.0.slim.js" integrity="sha256-M+GjhMBfXikM1izMplICCTscIj5hzPCp6uDzaypxtgg=" crossorigin="anonymous"></script>
<script>
    $(document).on("change", ".zonasi", function(e){
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        var zonasi = $(".zonasi").val();
        $.post("list-double", {zonasi:zonasi}, function(data){
            $(".content-double").html(data);
        })
    })
</script>