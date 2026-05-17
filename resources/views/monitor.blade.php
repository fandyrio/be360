<html>
    <head>
        <meta name="csrf-token" content="{{ csrf_token() }}">
    </head>
    <body>
        <h3><center>Monitor</center></h3>
        <div style="width:100%;float:left">
            <select class="zonasi">
                <option value=""></option>
                @for($x=0;$x<$jumlah;$x++)
                    <option value="{{ $zonasi[$x]['enc_id'] }}">{{ $zonasi[$x]['nama_zona'] }}</option>
                @endfor
            </select>
            <hr />
        </div>
        <div class="content-double" style="width: 50%;float:left;">
              
        </div>
         <div class="list-peserta" style="width: 50%;float:left;">
            
        </div>
        <div class="list-404" style="width: 50%;float:left;">
            
        </div>
    </body>
</html>
<script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
<script>
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    $(document).on("change", ".zonasi", function(e){
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        var zonasi = $(".zonasi").val();
        $(".list-404").html("Loading ...");
        $(".content-double").html("Loading ...");
        
        $.post("list-not-found", {zonasi:zonasi}, function(data){
            $(".list-404").html(data);
        })

        $.post("list-double", {zonasi:zonasi}, function(data){
            $(".content-double").html(data);
        })
    })
</script>