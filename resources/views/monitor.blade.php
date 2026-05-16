<html>
    <head>

    </head>
    <body>
        <h5><center>Monitor</center></h5>
        <select class="zonasi">
            <option value=""></option>
            @for($x=0;$x<$jumlah;$x++)
                <option value="{{ $data[$x]['enc_id'] }}">{{ $zonasi[$x]['nama_zona'] }}</option>
            @endfor
        </select>
    </body>
</html>
<script src="https://code.jquery.com/jquery-4.0.0.slim.js" integrity="sha256-M+GjhMBfXikM1izMplICCTscIj5hzPCp6uDzaypxtgg=" crossorigin="anonymous"></script>