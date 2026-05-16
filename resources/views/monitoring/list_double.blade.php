<h4>Double Insert</h4>
@if($jumlah === 0)
    <i>Clear</i>
@else
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Jabatan</th>
                <th>Satker</th>
            </tr>
        </thead>
        <tbody>
            @for($x=0;$x<$jumlah;$x++)
                <tr>
                    <td>{{ $x+1 }}</td>
                    <td>{{ $$data[$x]['nama_pegawai'] }}</td>
                    <td>{{ $data[$x]['jabatan'] }}</td>
                    <td>{{ $data[$x]['nama_satker'] }}</td>
                </tr>
            @endfor
        </tbody>
    </table>
@endif