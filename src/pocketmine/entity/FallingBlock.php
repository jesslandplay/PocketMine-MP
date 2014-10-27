<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____  
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \ 
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/ 
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_| 
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 * 
 *
*/

namespace pocketmine\entity;


use pocketmine\block\Block;
use pocketmine\event\entity\EntityBlockChangeEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\Byte;
use pocketmine\nbt\tag\Int;
use pocketmine\nbt\tag\String;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\SetEntityMotionPacket;
use pocketmine\Player;

class FallingBlock extends Entity{

	const NETWORK_ID = 66;

	public $width = 0.98;
	public $length = 0.98;
	public $height = 0.98;

	protected $gravity = 0.04;
	protected $drag = 0.02;
	protected $blockId = 0;
	protected $damage;

	public $canCollide = false;

	protected function initEntity(){
		$this->namedtag->id = new String("id", "FallingSand");
		if(isset($this->namedtag->TileID)){
			$this->blockId = $this->namedtag["TileID"];
		}
		elseif(isset($this->namedtag->Tile)){
			$this->blockId = $this->namedtag["Tile"];
			$this->namedtag["TileID"] = new Int("TileID", $this->blockId);
		}

		if($this->blockId === 0){
			$this->close();
		}
	}

	public function canCollideWith(Entity $entity){
		return false;
	}

	public function getData(){
		return [];
	}

	public function onUpdate($currentTick){

		if($this->closed){
			return false;
		}

		$this->timings->startTiming();

		$tickDiff = max(1, $currentTick - $this->lastUpdate);
		$this->lastUpdate = $currentTick;

		$hasUpdate = $this->entityBaseTick($tickDiff);

		if(!$this->dead){
			if($this->ticksLived === 1){
				$block = $this->level->getBlock($pos = Vector3::createVector($this->x, $this->y, $this->z)->floor());
				if($block->getID() != $this->blockId){
					$this->kill();
					return true;
				}
				$this->level->setBlock($pos, Block::get(0), true);

			}

			$this->motionY -= $this->gravity;

			$this->move($this->motionX, $this->motionY, $this->motionZ);

			$friction = 1 - $this->drag;

			$this->motionX *= $friction;
			$this->motionY *= 1 - $this->drag;
			$this->motionZ *= $friction;

			$pos = Vector3::createVector($this->x, $this->y, $this->z)->floor();

			if($this->onGround){
				$this->kill();
				$block = $this->level->getBlock($pos);
				if(!$block->isFullBlock){
					$this->getLevel()->dropItem($this, Item::get($this->getBlock(), $this->getDamage(), 1));
				}else{
					$this->server->getPluginManager()->callEvent($ev = new EntityBlockChangeEvent($this, $block, Block::get($this->getBlock(), $this->getDamage())));
					if(!$ev->isCancelled()){
						$this->getLevel()->setBlock($pos, $ev->getTo(), true);
					}
				}
				$hasUpdate = true;
			}

			$this->updateMovement();
		}

		return $hasUpdate or !$this->onGround or $this->motionX != 0 or $this->motionY != 0 or $this->motionZ != 0;
	}

	public function getBlock(){
		return $this->blockId;
	}
	public function getDamage(){
		return $this->damage;
	}

	public function saveNBT(){
		$this->namedtag->TileID = new Int("TileID", $this->blockId);
		$this->namedtag->Data = new Byte("Data", $this->damage);
	}

	public function attack($damage, $source = EntityDamageEvent::CAUSE_MAGIC){

	}

	public function heal($amount, $source = EntityRegainHealthEvent::CAUSE_MAGIC){

	}

	public function spawnTo(Player $player){
		$pk = AddEntityPacket::getFromPool();
		$pk->type = FallingBlock::NETWORK_ID;
		$pk->eid = $this->getID();
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->did = -$this->getBlock();
		$player->dataPacket($pk);

		$pk = SetEntityMotionPacket::getFromPool();
		$pk->entities = [
			[$this->getID(), $this->motionX, $this->motionY, $this->motionZ]
		];
		$player->dataPacket($pk);

		parent::spawnTo($player);
	}
}
