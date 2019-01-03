<h1>Maintenance Visits</h1>
<p><a href="<?=app('url')?>/exec/maintenance/form/">Add New Maintenance Visit</a></p>
<?pager()?>
<table id='sortTable' class='tablesorter' border='0'>
<thead>
	<tr>
		<th>Date</th>
		<th>Docket No</th>
		<th>Maintenance Id</th>
		<th>Job Name</th>
        <th>Job Address</th>
		<th>Group</th>
        <th>Technician</th>
		<th></th>
	</tr>
</thead>
	<tbody>
		<? foreach($result as $row){?>
			<tr>
			<td><?=toDate($row["maintenance_date"])?></td>
			<td><?=$row["docket_no"]?></td>
			<td><?=$row["maintenance_id"]?></td>
			<td><?=$row["job_name"]?></td>
            <td><?=$row["job_address_number"]?> <?=$row["job_address"]?>, <?=$row["job_suburb"]?></td>
            <td><?=$row["job_group"]?></td>
			<td><?=$row["technician_name"]?></td>
			<td>
				<a href="<?=app('url')?>/exec/maintenance/form/?frm_maintenance_id=<?=$row["maintenance_id"]?>" style="font-size:20px">Edit</a> | 
				<a href="<?=app('url')?>/exec/maintenance/delete/?frm_maintenance_id=<?=$row["maintenance_id"]?>" class="confirm">Delete</a>
			</td>
			</tr>
		<? }?>
	</tbody>
</table>
