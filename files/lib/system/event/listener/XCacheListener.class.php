<?php
namespace wcf\system\event\listener;
use wcf\system\event\IEventListener;
use wcf\system\exception\SystemException;
use wcf\system\Regex;
use wcf\system\WCF;

/**
 * @author      Jan Altensen (Stricted)
 * @copyright   2013-2014 Jan Altensen (Stricted)
 * @license     GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package     be.bastelstu.jan.wcf.xcache
 * @category    Community Framework
 */
class XCacheListener implements IEventListener {
	/**
	 * @see \wcf\system\event\IEventListener::execute()
	 */
	public function execute($eventObj, $className, $eventName) {
		switch ($className) {
			case "wcf\acp\page\CacheListPage":
				if ($eventObj->cacheData['source'] == 'wcf\system\cache\source\XCacheSource') {
					
					// set version
					$eventObj->cacheData['version'] = phpversion('xcache');
					
					$prefix = new Regex('^WCF_'.substr(sha1(WCF_DIR), 0, 10) . '_');
					$data = array();
					$info = xcache_list(1, 0);
					foreach ($info['cache_list'] as $cache) {
						if (!$prefix->match($cache['name'])) continue;
						
						// get additional cache information
						$data['data']['xCache'][] = array(
							'filename' => $prefix->replace($cache['name'], ''),
							'filesize' => $cache['size'],
							'mtime' => $cache['ctime']
						);
						$eventObj->cacheData['files']++;
						$eventObj->cacheData['size'] += $cache['size'];
					}
					$eventObj->caches = array_merge($data, $eventObj->caches);
				}
				break;
			
			case "wcf\system\option\OptionHandler":
				$eventObj->cachedOptions['cache_source_type']->modifySelectOptions($eventObj->cachedOptions['cache_source_type']->selectOptions . "\nx:wcf.acp.option.cache_source_type.x");
				
				/* dirty but i need wait for pull request https://github.com/WoltLab/WCF/pull/1630 */
				$eventObj->cachedOptions['cache_source_type']->enableOptions = $eventObj->cachedOptions['cache_source_type']->enableOptions . "\nx:!cache_source_memcached_host";
				break;
			
			case "wcf\acp\action\UninstallPackageAction":
				$packageID = 0;
				if (isset($_POST['packageID']) && !empty($_POST['packageID'])) $packageID = intval($_POST['packageID']);
				
				if ($packageID) {
					$sql = "SELECT * FROM wcf".WCF_N."_package where package = ? LIMIT 1";
					$statement = WCF::getDB()->prepareStatement($sql);
					$statement->execute(array("be.bastelstu.jan.wcf.xcache"));
					$row = $statement->fetchArray();
					if ($packageID == $row['packageID']) {
						// set cache to disk if apc(u) is enabled
						$sql = "UPDATE	wcf".WCF_N."_option
							SET	optionValue = ?
							WHERE	optionName = ?
								AND optionValue = ?";
						$statement = WCF::getDB()->prepareStatement($sql);
						$statement->execute(array(
							'disk',
							'cache_source_type',
							'x'
						));
					}
				}
				break;
		}
	}
}
