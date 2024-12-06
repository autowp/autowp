<?php

namespace Application\Model;

use Autowp\Image;
use Aws\S3\S3Client;
use Collator;
use Exception;
use ImagickException;
use Laminas\Db\Sql;

use function array_replace;
use function Autowp\Commons\currentFromResultSetInterface;
use function ceil;
use function chmod;
use function count;
use function escapeshellarg;
use function exec;
use function fclose;
use function file_exists;
use function file_put_contents;
use function floor;
use function fopen;
use function implode;
use function is_numeric;
use function mkdir;
use function preg_match;
use function sprintf;
use function sqrt;
use function str_replace;
use function sys_get_temp_dir;
use function tempnam;
use function usort;

class Brand
{
    public const MAX_FULLNAME = 255;

    private const ICON_FORMAT = 'brandicon';

    private Item $item;

    private array $collators = [];

    public function __construct(Item $item)
    {
        $this->item = $item;
    }

    private function getCollator(string $language): Collator
    {
        if (! isset($this->collators[$language])) {
            $this->collators[$language] = new Collator($language);
        }

        return $this->collators[$language];
    }

    private function compareName(string $a, string $b, string $language): int
    {
        $coll = $this->getCollator($language);
        switch ($language) {
            case 'zh':
                $aIsHan = (bool) preg_match("/^\p{Han}/u", $a);
                $bIsHan = (bool) preg_match("/^\p{Han}/u", $b);

                if ($aIsHan && ! $bIsHan) {
                    return -1;
                }

                if ($bIsHan && ! $aIsHan) {
                    return 1;
                }

                return $coll->compare($a, $b);

            default:
                return $coll->compare($a, $b);
        }
    }

    /**
     * @throws Exception
     */
    private function fetchBrand(string $language, callable $callback): ?array
    {
        $select = $this->item->getSelect([
            'language'     => $language,
            'columns'      => ['id', 'catname', 'name', 'full_name', 'logo_id'],
            'item_type_id' => Item::BRAND,
        ]);

        $callback($select);

        $brand = currentFromResultSetInterface($this->item->getTable()->selectWith($select));

        if (! $brand) {
            return null;
        }

        return [
            'id'        => $brand['id'],
            'name'      => $brand['name'],
            'catname'   => $brand['catname'],
            'full_name' => $brand['full_name'],
            'logo_id'   => $brand['logo_id'],
        ];
    }

    /**
     * @throws Exception
     */
    public function getBrandById(int $id, string $language): ?array
    {
        return $this->fetchBrand($language, function (Sql\Select $select) use ($id): void {
            $select->where(['item.id' => $id]);
        });
    }

    /**
     * @throws Exception
     */
    public function getList(array $options, ?callable $callback = null): array
    {
        $defaults = [
            'language' => 'en',
            'columns'  => [],
        ];
        $options  = array_replace($defaults, $options);

        $columns = [
            'id',
            'catname',
            'position',
            'name',
        ];
        foreach ($options['columns'] as $column => $expr) {
            switch ($expr) {
                case 'id':
                case 'name':
                    break;
                case 'logo_id':
                    $columns[] = 'logo_id';
                    break;
                default:
                    if (is_numeric($column)) {
                        $columns[] = $expr;
                    } else {
                        $columns[$column] = $expr;
                    }
            }
        }

        $select = $this->item->getSelect([
            'language'     => (string) $options['language'],
            'columns'      => $columns,
            'item_type_id' => Item::BRAND,
            'order'        => 'item.position',
        ]);

        if ($callback) {
            $callback($select);
        }

        $items = [];
        foreach ($this->item->getTable()->selectWith($select) as $row) {
            $items[] = $row;
        }

        usort($items, function ($a, $b) use ($options) {
            if ($a['position'] !== $b['position']) {
                return $a['position'] < $b['position'] ? -1 : 1;
            }

            return $this->compareName((string) $a['name'], (string) $b['name'], $options['language']);
        });

        return $items;
    }

    /**
     * @throws Image\Storage\Exception
     * @throws ImagickException
     * @throws Exception
     */
    public function createIconsSprite(Image\Storage $imageStorage, S3Client $s3, string $bucket): void
    {
        $list = $this->getList([
            'language' => 'en',
            'columns'  => [
                'logo_id',
            ],
        ], function ($select): void {
            $select->where(['logo_id']);
        });

        $images = [];

        $format = $imageStorage->getFormat(self::ICON_FORMAT);

        $background = $format->getBackground();

        $tmpDir = sys_get_temp_dir() . '/brands-sprite/';
        if (! file_exists($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        foreach ($list as $brand) {
            $stream = false;
            if ($brand['logo_id']) {
                $img    = $imageStorage->getFormatedImage($brand['logo_id'], self::ICON_FORMAT);
                $stream = $imageStorage->getImageBlobStream($img->getId());
            }

            if ($stream) {
                $catname          = str_replace('.', '_', $brand['catname']);
                $path             = $tmpDir . $catname . '.png';
                $images[$catname] = escapeshellarg($path);

                $success = file_put_contents($path, $stream);
                if ($success === 0) {
                    throw new Exception("Failed to download");
                }
            }
        }

        $count = count($images);
        $width = (int) ceil(sqrt($count));
        if ($width <= 0) {
            $width = 1;
        }

        $destImg = $tmpDir . 'brands.png';
        $destCss = $tmpDir . 'brands.css';

        $cmd = sprintf(
            'montage ' . implode(' ', $images) . ' -background %s -geometry +1+1 -tile %dx %s',
            escapeshellarg($background ?: 'none'),
            $width,
            escapeshellarg($destImg)
        );

        $cmdFilename = tempnam(sys_get_temp_dir(), 'brandicons');
        file_put_contents($cmdFilename, $cmd);
        chmod($cmdFilename, 0700);
        exec($cmdFilename);

        $css   = [];
        $index = 0;
        foreach ($images as $catname => $img) {
            $top   = floor($index / $width);
            $left  = $index - $top * $width;
            $css[] = sprintf(
                '.brandicon.brandicon-%s {background-position: -%dpx -%dpx}',
                $catname,
                1 + ($format->getWidth() + 1 + 1) * $left,
                1 + ($format->getHeight() + 1 + 1) * $top
            );
            $index++;
        }

        file_put_contents($destCss, implode(' ', $css));

        $files = [
            $destCss => [
                'path' => 'brands.css',
                'mime' => 'text/css',
            ],
            $destImg => [
                'path' => 'brands.png',
                'mime' => 'image/png',
            ],
        ];

        foreach ($files as $src => $dst) {
            $handle = fopen($src, 'r');
            $s3->putObject([
                'Key'         => $dst['path'],
                'Body'        => $handle,
                'Bucket'      => $bucket,
                'ACL'         => 'public-read',
                'ContentType' => $dst['mime'],
            ]);
            fclose($handle);
        }
    }
}
