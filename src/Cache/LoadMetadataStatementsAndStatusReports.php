<?php

declare(strict_types=1);

namespace App\Cache;

use App\Repository\MetadataStatementRepository;
use App\Repository\StatusReportRepository;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Webauthn\MetadataService\Service\FidoAllianceCompliantMetadataService;
use Webauthn\MetadataService\Service\MetadataService;
use Webauthn\MetadataService\Statement\MetadataStatement;

class LoadMetadataStatementsAndStatusReports implements CacheWarmerInterface
{
    /**
     * @param MetadataService[] $services
     */
    public function __construct(
        private MetadataStatementRepository $metadataStatementRepository,
        private StatusReportRepository $statusReportRepository,
        private iterable $services,
    ) {
    }

    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir): void
    {
        foreach ($this->services as $service) {
            foreach ($service->list() as $aaguid) {
                /** @var MetadataStatement $mds */
                $mds = $service->get($aaguid);
                $this->metadataStatementRepository->save($mds);

                if ($service instanceof FidoAllianceCompliantMetadataService) {
                    $statusReports = $service->getStatusReports($mds->getAaguid());
                    $this->statusReportRepository->save($mds->getAaguid(), $statusReports);
                }
            }
        }
    }
}
