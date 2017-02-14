<?
class redsms
{
	public static function Signature($params, $api_key)
	{
		ksort($params);
		reset($params);
		return md5(implode($params).$api_key);
	}
}
?>