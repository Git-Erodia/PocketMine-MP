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

namespace pocketmine\inventory\transaction;

use pocketmine\crafting\CraftingManager;
use pocketmine\crafting\CraftingRecipe;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\item\Item;
use pocketmine\player\Player;
use function array_pop;
use function count;
use function intdiv;

/**
 * This transaction type is specialized for crafting validation. It shares most of the same semantics of the base
 * inventory transaction type, but the requirement for validity is slightly different.
 *
 * It is expected that the actions in this transaction type will produce an **unbalanced result**, i.e. some inputs won't
 * have corresponding outputs, and vice versa. The reason why is because the unmatched inputs are recipe inputs, and
 * the unmatched outputs are recipe results.
 *
 * Therefore, the validity requirement is that the imbalance of the transaction should match the expected inputs and
 * outputs of a registered crafting recipe.
 *
 * This transaction allows multiple repetitions of the same recipe to be crafted in a single batch. In the case of batch
 * crafting, the number of unmatched inputs and outputs must be exactly divisible by the expected recipe ingredients and
 * results, with no remainder. Any leftovers are expected to be emitted back to the crafting grid.
 */
class CraftingTransaction extends InventoryTransaction{
	/** @var CraftingRecipe|null */
	protected $recipe;
	/** @var int|null */
	protected $repetitions;

	/** @var Item[] */
	protected $inputs = [];
	/** @var Item[] */
	protected $outputs = [];

	private CraftingManager $craftingManager;

	public function __construct(Player $source, CraftingManager $craftingManager, array $actions = [], ?CraftingRecipe $recipe = null, ?int $repetitions = null){
		parent::__construct($source, $actions);
		$this->craftingManager = $craftingManager;
		$this->recipe = $recipe;
		$this->repetitions = $repetitions;
	}

	/**
	 * @param Item[] $txItems
	 * @param Item[] $recipeItems
	 *
	 * @throws TransactionValidationException
	 */
	protected function matchRecipeItems(array $txItems, array $recipeItems, bool $wildcards, int $iterations = 0) : int{
		if(count($recipeItems) === 0){
			throw new TransactionValidationException("No recipe items given");
		}
		if(count($txItems) === 0){
			throw new TransactionValidationException("No transaction items given");
		}

		while(count($recipeItems) > 0){
			/** @var Item $recipeItem */
			$recipeItem = array_pop($recipeItems);
			$needCount = $recipeItem->getCount();
			foreach($recipeItems as $i => $otherRecipeItem){
				if($otherRecipeItem->canStackWith($recipeItem)){ //make sure they have the same wildcards set
					$needCount += $otherRecipeItem->getCount();
					unset($recipeItems[$i]);
				}
			}

			$haveCount = 0;
			foreach($txItems as $j => $txItem){
				if($txItem->equals($recipeItem, !$wildcards || !$recipeItem->hasAnyDamageValue(), !$wildcards || $recipeItem->hasNamedTag())){
					$haveCount += $txItem->getCount();
					unset($txItems[$j]);
				}
			}

			if($haveCount % $needCount !== 0){
				//wrong count for this output, should divide exactly
				throw new TransactionValidationException("Expected an exact multiple of required $recipeItem (given: $haveCount, needed: $needCount)");
			}

			$multiplier = intdiv($haveCount, $needCount);
			if($multiplier < 1){
				throw new TransactionValidationException("Expected more than zero items matching $recipeItem (given: $haveCount, needed: $needCount)");
			}
			if($iterations === 0){
				$iterations = $multiplier;
			}elseif($multiplier !== $iterations){
				//wrong count for this output, should match previous outputs
				throw new TransactionValidationException("Expected $recipeItem x$iterations, but found x$multiplier");
			}
		}

		if(count($txItems) > 0){
			//all items should be destroyed in this process
			throw new TransactionValidationException("Expected 0 ingredients left over, have " . count($txItems));
		}

		return $iterations;
	}

	private function validateRecipe(CraftingRecipe $recipe, ?int $expectedRepetitions) : int{
		//compute number of times recipe was crafted
		$repetitions = $this->matchRecipeItems($this->outputs, $recipe->getResultsFor($this->source->getCraftingGrid()), false);
		if($expectedRepetitions !== null && $repetitions !== $expectedRepetitions){
			throw new TransactionValidationException("Expected $expectedRepetitions repetitions, got $repetitions");
		}
		//assert that $repetitions x recipe ingredients should be consumed
		$this->matchRecipeItems($this->inputs, $recipe->getIngredientList(), true, $repetitions);

		return $repetitions;
	}

	public function validate() : void{
		$this->squashDuplicateSlotChanges();
		if(count($this->actions) < 1){
			throw new TransactionValidationException("Transaction must have at least one action to be executable");
		}

		$this->matchItems($this->outputs, $this->inputs);

		if($this->recipe === null){
			$failed = 0;
			foreach($this->craftingManager->matchRecipeByOutputs($this->outputs) as $recipe){
				try{
					$this->repetitions = $this->validateRecipe($recipe, $this->repetitions);
					$this->recipe = $recipe;
					break;
				}catch(TransactionValidationException $e){
					//failed
					++$failed;
				}
			}

			if($this->recipe === null){
				throw new TransactionValidationException("Unable to match a recipe to transaction (tried to match against $failed recipes)");
			}
		}else{
			$this->repetitions = $this->validateRecipe($this->recipe, $this->repetitions);
		}
	}

	protected function callExecuteEvent() : bool{
		$ev = new CraftItemEvent($this, $this->recipe, $this->repetitions, $this->inputs, $this->outputs);
		$ev->call();
		return !$ev->isCancelled();
	}
}
