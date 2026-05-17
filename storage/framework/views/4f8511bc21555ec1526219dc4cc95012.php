<h4><?php echo e($title); ?></h4>
<div class="progress_msg" style="color:red;"></div>
<?php if($jumlah === 0): ?>
    <i>Clear</i>
<?php else: ?>
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
            <?php for($x=0;$x<$jumlah;$x++): ?>
                <tr>
                    <td><?php echo e($x+1); ?></td>
                    <td><?php echo e($data[$x]['nama_pegawai']); ?></td>
                    <td><?php echo e($data[$x]['jabatan']); ?></td>
                    <td><?php echo e($data[$x]['nama_satker']); ?></td>
                    <td><?php echo e($data[$x]['endpoint']); ?></td>
                    <td><button class='<?php echo e($class_fix); ?>' data-target="<?php echo e($data[$x]['target']); ?>">Fix</td>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>
<?php endif; ?>
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
</script><?php /**PATH /var/www/html/be_360/be360/resources/views/monitoring/list_not_found.blade.php ENDPATH**/ ?>