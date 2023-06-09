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

declare(strict_types=1);

namespace pocketmine\player;

use pocketmine\math\Vector3;
use pocketmine\world\TickingChunkLoader;

/**
 * @deprecated This class was only needed to implement TickingChunkLoader, which is now deprecated.
 * ChunkTicker should be registered on ticking chunks to make them tick instead.
 */
final class PlayerChunkLoader implements TickingChunkLoader{
	public function __construct(private Vector3 $currentLocation){}

	public function setCurrentLocation(Vector3 $currentLocation) : void{
		$this->currentLocation = $currentLocation;
	}

	public function getX() : float{
		return $this->currentLocation->getFloorX();
	}

	public function getZ() : float{
		return $this->currentLocation->getFloorZ();
	}
}
