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
        $sql = "SELECT count(*) FROM `services` LEFT JOIN `package_pricing` ON `services`.`pricing_id`=`package_pricing`.`id` LEFT JOIN `package_group` on `package_pricing`.`package_id`=`package_group`.`package_id` WHERE `services`.`status` IN ('active','suspended') AND `package_group`.`package_group_id` IN ($package_group_id)";
        $result = $this->db->query($sql);
        return $result->fetch_row()[0];
    }

    /**
     * Counts services active for a given config opt and determines their rev/mo value (coupons not taken into account)
     * @param $name string name of the configurable option
     * @param $values array values used in mysql IN() for matching paid values
     * @return mixed
     */
    public function configOptRev($name,$values=[1]){
        $values_csv = implode(',',$values);
        $sql ="select sum(count) as count,sum(price) as rev from (select count(*) as count,sum(round(pricings.price/term)) as price from service_options left join services on services.id=service_options.service_id left join package_option_pricing on service_options.option_pricing_id=package_option_pricing.id left join package_option_values on package_option_pricing.option_value_id=package_option_values.id left join package_options on package_option_values.option_id=package_options.id left join pricings on package_option_pricing.pricing_id=pricings.id where package_options.name='$name' and services.status in ('active','suspended') and package_option_values.value IN ($values_csv) and period='month' union select count(*) as count,sum(round(pricings.price/term/12)) as price from service_options left join services on services.id=service_options.service_id left join package_option_pricing on service_options.option_pricing_id=package_option_pricing.id left join package_option_values on package_option_pricing.option_value_id=package_option_values.id left join package_options on package_option_values.option_id=package_options.id left join pricings on package_option_pricing.pricing_id=pricings.id where package_options.name='$name' and services.status in ('active','suspended') and package_option_values.value IN ($values_csv) and period='year') as t1;";
        $result = $this->db->query($sql);
        return $result->fetch_assoc();
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

if(defined('CALC_CONFIG_OPTS')) {
    $html .= "<br><br>Monthly 'value' of configurable options.  Rev represents current monthly revenue <strong>not YTD</strong> from a given configurable option.  <strong>This does not take into account coupons.</strong>  3-mo, 6-mo, yearly, etc. are broken down to monthly figures.<br>";
    $html .= "<table border='1'>
	<tr>
		<th>Option</th>
		<th>Revenue/mo (no coupon)</th>
		<th>Current Active</th>
	</tr>";
    foreach (CALC_CONFIG_OPTS as $opt) {
        $values = [1];
        if (isset($opt['values']))
            $values = $opt['values'];
        $configOptRev = $revcron->configOptRev($opt['name'], $values);

        $html .= "<tr>
                <td><strong>" . $opt['name'] . "</strong></td>
                <td class='align-right'>$" . number_format($configOptRev['rev'],2,'.',',') . "</td>
                <td class='align-right'>" . $configOptRev['count'] . "</td>
            </tr>";
    }
}

mail(EMAIL_TO,EMAIL_TITLE,$html, "Content-Type: text/html; charset=ISO-8859-1\r\n");
