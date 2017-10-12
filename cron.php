<?php

require_once('config.php');

class blestaRevCron{

	/**
	 * blestaRevCron constructor.
	 */
	public function __construct(){
		$this->db = new mysqli(MYSQL_HOST,MYSQL_USER,MYSQL_PASSWORD,MYSQL_DATABASE) or die(mysqli_error($this->db));
	}

	/**
	 * Fetches a listing of all package groups from the database
	 * @return array|bool array of package groups in id=>name format.  false on failure
	 */
	public function getPackageGroups(){
		$sql = "SELECT `id`,`name` from `package_groups`";
		$result = $this->db->query($sql);
		while($row = $result->fetch_assoc()){
			$package_groups[$row['id']] = $row['name'];
		}
		if(isset($package_groups) && is_array($package_groups))
			return $package_groups;
		return false;
	}

	/**
	 * Fetches revenue for a given year and package group.  year=null will use current year
	 * @param $package_group_id int
	 * @param null|int $year
	 * @return float
	 */
	public function getGroupRevenue($package_group_id,$year=null){
		if(is_null($year))
			$year = date('Y');
		$sql = "SELECT sum(`paid`) FROM `services` LEFT JOIN `invoice_lines` ON `invoice_lines`.`service_id`=`services`.`id` LEFT JOIN `invoices` ON `invoice_lines`.`invoice_id`=`invoices`.`id` LEFT JOIN `package_pricing` ON `services`.`pricing_id`=`package_pricing`.`id` LEFT JOIN `package_group` on `package_pricing`.`package_id`=`package_group`.`package_id` WHERE `invoice_lines`.`order`=0 AND `invoices`.`paid` > 0 AND `invoices`.`status`='active' AND YEAR(`invoices`.`date_closed`) = $year AND `package_group`.`package_group_id` IN ($package_group_id)";
		$result = $this->db->query($sql);
		return round($result->fetch_row()[0],2);
	}

	/**
	 * Counts active services for the given group id
	 * @param $package_group_id int
	 * @return mixed
	 */
	public function countServices($package_group_id){
		$sql = "SELECT count(*) FROM `services` LEFT JOIN `package_pricing` ON `services`.`pricing_id`=`package_pricing`.`id` LEFT JOIN `package_group` on `package_pricing`.`package_id`=`package_group`.`package_id` WHERE `services`.`status`='active' AND `package_group`.`package_group_id` IN ($package_group_id)";
		$result = $this->db->query($sql);
		return $result->fetch_row()[0];
	}

}

$revcron = new blestaRevCron;

$package_groups = $revcron->getPackageGroups();
$total_rev = 0;
$total_services = 0;

foreach($package_groups as $group_id=>$group_name){
	$rev = $revcron->getGroupRevenue($group_id);
	$count = $revcron->countServices($group_id);
	$group_stats[$group_id] = array(
		'name' => $group_name,
		'revenue_ytd' => $rev,
		'active_services' => $count
	);

	// TODO: re-do combine groups logic. there's probably a much better way to do this
	foreach(COMBINE_GROUPS as $groups){
		if(array_search($group_id,$groups) && $group_id != $groups[0]){
			$group_stats[$groups[0]] = array(
				'name' => $group_stats[$groups[0]]['name'],
				'revenue_ytd' => $group_stats[$groups[0]]['revenue_ytd'] + $rev,
				'active_services' => $group_stats[$groups[0]]['active_services'] + $count,
			);
			unset($group_stats[$group_id]);
		}
	}

	$total_rev = $total_rev + $rev;
	$total_services = $total_services + $count;
}
$total_rev = number_format($total_rev,2,'.',',');

// TODO: clean up html mess

$html = '';

$html .= "<style>
.align-right{text-align:right;}
</style>";

$html .= "<table border='1'>
	<tr>
		<th>Group Name</th>
		<th>YTD Revenue</th>
		<th>Current Active Services</th>
	</tr>";

	foreach($group_stats as $stat){
		$html .= "<tr>
			<td><b>" . $stat['name'] . "</b></td>
			<td class='align-right'>$" . number_format($stat['revenue_ytd'],2,'.',',') . "</td>
			<td class='align-right'>" . $stat['active_services'] . "</td>
		</tr>";
	}
	$html .= "<tr>
<td><b>Total</b></td>
<td class='align-right'>$" . $total_rev . "</td>
<td class='align-right'>" . $total_services . "</td>
</tr>";
$html .= "</table>";

mail(EMAIL_TO,EMAIL_TITLE,$html, "Content-Type: text/html; charset=ISO-8859-1\r\n");