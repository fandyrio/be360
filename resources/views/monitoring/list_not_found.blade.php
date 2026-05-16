<h4>Link 404</h4>
<div class="progress_msg" style="color:red;"></div>
@if($jumlah === 0)
    <i>Clear</i>
@else
    <table style="border: 1px solid black;">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Jabatan</th>
                <th>Satker</th>
                <th>Endpoint</th>
            </tr>
        </thead>
        <tbody>
            @for($x=0;$x<$jumlah;$x++)
                <tr>
                    <td>{{ $x+1 }}</td>
                    <td>{{ $data[$x]['nama_pegawai'] }}</td>
                    <td>{{ $data[$x]['jabatan'] }}</td>
                    <td>{{ $data[$x]['nama_satker'] }}</td>
                    <td>{{ $data[$x]['endpoint'] }}</td>
                    <td><button class='fix' data-target="{{ $data[$x]['id_observee'] }}">Fix</td>
                </tr>
            @endfor
        </tbody>
    </table>
@endif
<script>
    $(document).on("click", ".fix", function(e){
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        var target=$(this).data('target');
        $.ajax({
            beforeSend:function(){
                $(".progress_msg").html("Loading ...");
            },
            url:'fix-404',
            data:{'target':target},
            type:'POST',
            dataType:'JSON',
            success:function(data){
                $(".progress_msg").html(data.msg);
                $(".fix[data-target='"+target+"']").remove();
            },error:function(data){
                alert("Error data");
                console.log(data);
            }
        })
    });
</script>