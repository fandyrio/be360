<h4>Double Insert</h4>
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
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>
<?php endif; ?><?php /**PATH /var/www/html/be_360/be360/resources/views/monitoring/list_double.blade.php ENDPATH**/ ?>