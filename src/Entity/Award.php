<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\RaffleCoreBundle\Repository\AwardRepository;

/**
 * 奖品
 */
#[ORM\Entity(repositoryClass: AwardRepository::class)]
#[ORM\Table(name: 'raffle_award', options: ['comment' => '奖品'])]
#[ORM\Index(columns: ['pool_id', 'valid'], name: 'raffle_award_idx_award_pool_valid')]
class Award implements \Stringable
{
    use TimestampableAware;
    use BlameableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '奖品ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Pool::class, inversedBy: 'awards')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '奖池不能为空')]
    private ?Pool $pool = null;

    #[ORM\ManyToOne(targetEntity: Sku::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'SKU不能为空')]
    private ?Sku $sku = null;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '奖品名称'])]
    #[Assert\NotBlank(message: '奖品名称不能为空')]
    #[Assert\Length(max: 255, maxMessage: '奖品名称长度不能超过 {{ limit }} 字符')]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '奖品描述'])]
    #[Assert\Length(max: 65535, maxMessage: '奖品描述长度不能超过 {{ limit }} 字符')]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '中奖概率权重'])]
    #[Assert\GreaterThanOrEqual(value: 0, message: '概率权重不能为负数')]
    #[TrackColumn]
    private int $probability = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '总投放数量'])]
    #[Assert\GreaterThanOrEqual(value: 0, message: '投放数量不能为负数')]
    #[Assert\PositiveOrZero(message: '投放数量必须为非负数')]
    #[TrackColumn]
    private int $quantity = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '每日限制数量'])]
    #[Assert\GreaterThanOrEqual(value: 1, message: '每日限制至少1个')]
    private ?int $dayLimit = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '单次派发数量'])]
    #[Assert\GreaterThanOrEqual(value: 1, message: '单次派发数量至少1个')]
    #[Assert\PositiveOrZero(message: '派发数量必须为非负数')]
    private int $amount = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 2, options: ['comment' => '奖品价值'])]
    #[Assert\GreaterThanOrEqual(value: 0, message: '奖品价值不能为负数')]
    #[Assert\Length(max: 20, maxMessage: '奖品价值长度不能超过 {{ limit }} 字符')]
    private string $value = '0.00';

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否需要收货地址'])]
    #[Assert\Type(type: 'bool', message: '收货地址标识必须为布尔值')]
    private bool $needConsignee = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\Type(type: 'bool', message: '启用状态必须为布尔值')]
    #[TrackColumn]
    #[IndexColumn]
    private bool $valid = true;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '排序号'])]
    #[Assert\GreaterThanOrEqual(value: 0, message: '排序号不能为负数')]
    #[Assert\PositiveOrZero(message: '排序号必须为非负数')]
    #[IndexColumn]
    private int $sortNumber = 0;

    /**
     * @var Collection<int, Chance>
     */
    #[ORM\OneToMany(mappedBy: 'award', targetEntity: Chance::class)]
    private Collection $chances;

    public function __construct()
    {
        $this->chances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPool(): ?Pool
    {
        return $this->pool;
    }

    public function setPool(?Pool $pool): void
    {
        $this->pool = $pool;
    }

    public function getSku(): ?Sku
    {
        return $this->sku;
    }

    public function setSku(?Sku $sku): void
    {
        $this->sku = $sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getProbability(): int
    {
        return $this->probability;
    }

    public function setProbability(int $probability): void
    {
        $this->probability = $probability;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getDayLimit(): ?int
    {
        return $this->dayLimit;
    }

    public function setDayLimit(?int $dayLimit): void
    {
        $this->dayLimit = $dayLimit;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function isNeedConsignee(): bool
    {
        return $this->needConsignee;
    }

    public function setNeedConsignee(bool $needConsignee): void
    {
        $this->needConsignee = $needConsignee;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    public function getSortNumber(): int
    {
        return $this->sortNumber;
    }

    public function setSortNumber(int $sortNumber): void
    {
        $this->sortNumber = $sortNumber;
    }

    /**
     * @return Collection<int, Chance>
     */
    public function getChances(): Collection
    {
        return $this->chances;
    }

    public function addChance(Chance $chance): void
    {
        if (!$this->chances->contains($chance)) {
            $this->chances->add($chance);
            $chance->setAward($this);
        }
    }

    public function removeChance(Chance $chance): void
    {
        if ($this->chances->removeElement($chance)) {
            if ($chance->getAward() === $this) {
                $chance->setAward(null);
            }
        }
    }

    public function isAvailable(): bool
    {
        return $this->valid && $this->quantity > 0;
    }

    public function decreaseQuantity(int $amount = 1): bool
    {
        if ($this->quantity >= $amount) {
            $this->quantity -= $amount;

            return true;
        }

        return false;
    }

    public function __toString(): string
    {
        return '' !== $this->name ? $this->name : ($this->sku?->getSpu()?->getTitle() ?? '未命名奖品');
    }
}
