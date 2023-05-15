<?php declare(strict_types=1);

namespace DerivativeMedia\View\Helper;

use DerivativeMedia\Module;
use DerivativeMedia\Mvc\Controller\Plugin\TraitDerivative;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;

class HasDerivative extends AbstractHelper
{
    use TraitDerivative;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var array
     */
    protected $enabled;

    public function __construct(string $basePath, array $enabled)
    {
        $this->basePath = $basePath;
        $this->enabled = $enabled;
    }

    /**
     * List available derivatives of an item.
     *
     * Some derivatives are item level, some other media level, so the output
     * list all of them (in a future version).
     *
     * @todo Media level is useless for now and are not listed. Media data should be enough.
     *
     * Some derivative can be created dynamically.
     *
     * @return array Associative array with the resource id (item and medias) as
     * key and an array of derivative types as value. This array is a list of
     * derivative types as key and an array with:
     * - mode (string): file can be build as "static", "dynamic" or "live".
     * - ready (boolean): file is available
     * - in_progress (boolean): file will be available soon
     * - url (string): url of the file.
     */
    public function __invoke(?AbstractResourceEntityRepresentation $resource, ?string $type = null): array
    {
        $result = [];

        if ($type
            && (!isset(Module::DERIVATIVES[$type]) || !in_array($type, $this->enabled))
        ) {
            return [];
        }

        if ($resource instanceof MediaRepresentation) {
            // $medias = [$resource];
        } elseif ($resource instanceof ItemRepresentation) {
            // medias = $resource->media();
            $result[$resource->id()] = $this->hasDerivativeItem($resource, $type);
        } else {
            return [];
        }

        return $result;
    }

    /**
     * Get the list of type availables for items.
     *
     * Unlike media, item as no field in database to store data. So the check is
     * done directly on files.

     * @param \Omeka\Api\Representation\ItemRepresentation $item
     * @return array
     */
    protected function hasDerivativeItem(ItemRepresentation $item, ?string $type = null): array
    {
        $result = [];

        $itemId = $item->id();
        $url = $this->getView()->get('url');

        foreach ($type ? [$type] : $this->enabled as $type) {
            if (!isset(Module::DERIVATIVES[$type])
                || Module::DERIVATIVES[$type]['level'] === 'media'
            ) {
                continue;
            }

            $filepath = $this->basePath . '/' . $type . '/' . $item->id() . '.' . Module::DERIVATIVES[$type]['extension'];
            $tempFilepath =$this->tempFilepath($filepath);

            $size = null;

            $ready = file_exists($filepath) && is_readable($filepath) && ($size = filesize($filepath));
            $isInProgress = !$ready
                && file_exists($tempFilepath) && is_readable($tempFilepath) && filesize($tempFilepath);

            // Check if a derivative may be created.
            $feasible = $ready || $isInProgress;
            if (!$feasible) {
                $dataMedia = $this->dataMedia($item, $type);
                $feasible = !empty($dataMedia);
            }

            $result[$type] = [
                'mode' => Module::DERIVATIVES[$type]['mode'],
                'feasible' => $feasible,
                'in_progress' => $isInProgress,
                'ready' => $ready,
                'size' => $size,
                'url' => $ready || $isInProgress
                    ? $url('derivative', ['type' => $type, 'id' => $itemId])
                    : null,
            ];
        }

        return $result;
    }
}
