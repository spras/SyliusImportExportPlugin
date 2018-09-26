<?php
/**
 * Created by PhpStorm.
 * User: apitchen
 * Date: 17/01/18
 * Time: 11:41
 */

namespace FriendsOfSylius\SyliusImportExportPlugin\Processor;


use FriendsOfSylius\SyliusImportExportPlugin\Exception\ImporterException;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Factory\TaxonFactoryInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;

final class TaxonomyProcessor implements ResourceProcessorInterface
{

    /** @var TaxonFactoryInterface */
    private $resourceTaxonFactory;

    /** @var TaxonRepositoryInterface */
    private $taxonRepositry;

    /** @var  @var LocaleProviderInterface */
    private $localeProvider;

    /** @var MetadataValidatorInterface */
    private $metadataValidator;

    /** @var array */
    private $headerKeys;

    /** @var array */
    private $availableLocalesCodes;
    
    public function __construct(
        TaxonFactoryInterface $taxonFactory,
        TaxonRepositoryInterface $taxonRepository,
        LocaleProviderInterface $localeProviderInterface,
        MetadataValidatorInterface $metadataValidator,
        array $headerKeys
    ) {
        $this->resourceTaxonFactory = $taxonFactory;
        $this->taxonRepositry = $taxonRepository;
        $this->localeProviderInterface = $localeProviderInterface;
        $this->metadataValidator = $metadataValidator;
        $this->headerKeys = $headerKeys;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ImporterException
     */
    public function process(array $data): void
    {
        $this->metadataValidator->validateHeaders($this->headerKeys, $data);

        /** @var TaxonInterface $taxon */
        $taxon = $this->getTaxon($data['Code'], $data['Parent']);

        $locale = $this->getLocale($data['Locale']);
        $taxon->setCurrentLocale($locale);
        $taxon->setFallbackLocale($locale);

        $taxon->setName($data['Name']);
        $taxon->setSlug($data['Slug']);

        $descriptionValue = $data['Description'];
        if (strlen((string) $descriptionValue) === 0 && !is_bool($descriptionValue)) {
            $descriptionValue = null;
        }
        $taxon->setDescription($descriptionValue);

        $this->taxonRepositry->add($taxon);
    }

    private function getTaxon(string $code, string $parentCode): TaxonInterface
    {

        /** @var TaxonInterface $taxon */
        $taxon = $this->taxonRepositry->findOneBy(['code' => $code]);

        if ($taxon == null) {
            /** @var TaxonInterface $parentTaxon */
            $parentTaxon = $this->taxonRepositry->findOneBy(['code' => $parentCode]);
            if (null === $parentTaxon) {
                $taxon = $this->resourceTaxonFactory->createNew();
            } else {
                $taxon = $this->resourceTaxonFactory->createForParent($parentTaxon);
            }
            $taxon->setCode($code);
        }

        return $taxon;
    }

    private function getLocale(string $locale)
    {
        if ($locale === "") {
            return  $this->localeProviderInterface->getDefaultLocaleCode();
        }

        if (null === $this->availableLocalesCodes) {
            $this->availableLocalesCodes = $this->localeProviderInterface->getAvailableLocalesCodes();
        }

        if (in_array($locale,  $this->availableLocalesCodes)) {
            return $locale;
        } else {
            throw new \Exception("Locale $locale does not exist in Sylius");
        }
    }
}