<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 05/04/2016
 * Time: 14:25
 */

namespace Outlandish\SocialMonitor;


class KpiPdfLinker
{
	public function link()
	{
		return file_exists(APPLICATION_PATH . '/../data/uploads/kpis.pdf') ? 'data/uploads/kpis.pdf' : null; 
	}
}