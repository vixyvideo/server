<?php
/**
 * @package plugins.tvinciDistribution
 * @subpackage api.objects
 */
class KalturaTvinciDistributionJobProviderData extends KalturaConfigurableDistributionJobProviderData
{
	const DUR_24_HOURS_IN_SECS = 86400; // = 24h * 60m * 60s;

	/**
	 * @var string
	 */
	public $submitXml;

	/**
	 * @var string
	 */
	public $updateXml;

	/**
	 * @var string
	 */
	public $deleteXml;

	public function __construct(KalturaDistributionJobData $distributionJobData = null)
	{
		parent::__construct($distributionJobData);
	    
		if(!$distributionJobData)
			return;
			
		if(!($distributionJobData->distributionProfile instanceof KalturaTvinciDistributionProfile))
			return;
		
		$fieldValues = unserialize($this->fieldValues);
		
		$entry = entryPeer::retrieveByPK($distributionJobData->entryDistribution->entryId);
		$extraData = array(
				'entryId' => $entry->getId(),
				'createdAt' => $entry->getCreatedAtAsInt(), 
				'broadcasterName' => 'Kaltura-' . $entry->getPartnerId(), 
			);
		
		$thumbAssets = assetPeer::retrieveByIds(explode(',', $distributionJobData->entryDistribution->thumbAssetIds));
		$picRatios = array();
		foreach ( $thumbAssets as $thumbAsset )
		{
			$thumbDownloadUrl = $this->getAssetDownloadUrl( $thumbAsset );
			
			$ratio = KDLVideoAspectRatio::ConvertFrameSize($thumbAsset->getWidth(), $thumbAsset->getHeight());
			
			$picRatios[] = array(
					'url' => $thumbDownloadUrl,
					'ratio' => $ratio,
				);
			
			if ( $thumbAsset->hasTag(thumbParams::TAG_DEFAULT_THUMB) )
			{
				$extraData['defaultThumbUrl'] = $thumbDownloadUrl;
			}
		}

		$extraData['picRatios'] = $picRatios;
		if ( ! isset($extraData['defaultThumbUrl']) && count($picRatios) )
		{
			// Choose the URL of the first resource in the array
			$extraData['defaultThumbUrl'] = $picRatios[0]['url'];
		}

		$flavorAssets = assetPeer::retrieveByIds(explode(',', $distributionJobData->entryDistribution->flavorAssetIds));
		$assetInfo = array();
		foreach ( $flavorAssets as $flavorAsset )
		{
			$this->updateFlavorAssetInfo($assetInfo, $flavorAsset, $fieldValues);
		}
		
		if ( count($assetInfo) )
		{
			$extraData['assetInfo'] = $assetInfo;			
		}

		$feed = new TvinciDistributionFeedHelper($distributionJobData->distributionProfile, $fieldValues, $extraData);
		
		if ($distributionJobData instanceof KalturaDistributionSubmitJobData)
		{
			$this->submitXml = $feed->buildSubmitFeed();
		}
		elseif ($distributionJobData instanceof KalturaDistributionUpdateJobData)
		{
			$this->updateXml = $feed->buildUpdateFeed();
		}
		elseif ($distributionJobData instanceof KalturaDistributionDeleteJobData)
		{
			$this->deleteXml = $feed->buildDeleteFeed();
		}
	}

	private function updateFlavorAssetInfo(array &$assetInfo, $flavorAsset, $fieldValues)
	{
		$assetFlavorParams = assetParamsPeer::retrieveByPK( $flavorAsset->getFlavorParamsId() );
		$assetFlavorParamsName = $assetFlavorParams->getName();

		$videoAssetFieldNames = array(
				TvinciDistributionField::VIDEO_ASSET_MAIN,
				TvinciDistributionField::VIDEO_ASSET_TABLET_MAIN,
				TvinciDistributionField::VIDEO_ASSET_SMARTPHONE_MAIN,
			);
		
		foreach ( $videoAssetFieldNames as $videoAssetFieldName )
		{
			if ( isset($fieldValues[$videoAssetFieldName]) )
			{
				$configFlavorParamName = $fieldValues[$videoAssetFieldName];
				
				if ( $configFlavorParamName == $assetFlavorParamsName )
				{
					$assetInfo[$videoAssetFieldName] = array(
							'url' => $this->getAssetDownloadUrl($flavorAsset),
							'name' => $assetFlavorParamsName,
						);
					
					// Note: instead of 'break'ing here, we'll continue to loop in case
					//       the same flavor asset is required by another $videoAssetField
				}
			} 
		}
	}
	
	private function getAssetDownloadUrl($asset)
	{
		$downloadUrl = $asset->getDownloadUrlWithExpiry( self::DUR_24_HOURS_IN_SECS );
		$downloadUrl .= '/f/' . $asset->getId() . '.' . $asset->getFileExt();
		return $downloadUrl;
	}

	private static $map_between_objects = array
	(
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}
}
