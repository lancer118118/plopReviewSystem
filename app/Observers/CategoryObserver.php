<?php
/**
 * LaraClassifier - Classified Ads Web Application
 * Copyright (c) BeDigit. All Rights Reserved
 *
 * Website: https://laraclassifier.com
 *
 * LICENSE
 * -------
 * This software is furnished under a license and may be used and copied
 * only in accordance with the terms of such license and with the inclusion
 * of the above copyright notice. If you Purchased from CodeCanyon,
 * Please read the full License from here - http://codecanyon.net/licenses/standard
 */

namespace App\Observers;

use App\Helpers\Files\Storage\StorageDisk;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\Post;
use App\Observers\Traits\CategoryTrait;
use Illuminate\Support\Str;

class CategoryObserver
{
	use CategoryTrait;
	
	/**
	 * Listen to the Entry creating event.
	 *
	 * @param Category $category
	 * @return void
	 */
	public function creating(Category $category)
	{
		// Fix required columns
		$category = $this->fixRequiredColumns($category);
		
		// Apply the nested created actions
		return $this->creatingNestedItem($category);
	}
	
	/**
	 * Listen to the Entry updating event.
	 *
	 * @param Category $category
	 * @return void
	 */
	public function updating(Category $category)
	{
		// Fix required columns
		$category = $this->fixRequiredColumns($category);
		
		// Apply the nested updating actions
		return $this->updatingNestedItem($category);
	}
	
	/**
	 * Listen to the Entry deleting event.
	 *
	 * @param Category $category
	 * @return void
	 */
	public function deleting($category)
	{
		// Apply the nested deleting actions
		$this->deletingNestedItem($category);
		
		// Storage Disk Init.
		$disk = StorageDisk::getDisk();
		
		// Delete all the Category's Custom Fields
		$catFields = CategoryField::where('category_id', $category->id)->get();
		if ($catFields->count() > 0) {
			foreach ($catFields as $catField) {
				$catField->delete();
			}
		}
		
		// Delete all the Category's Posts
		$posts = Post::where('category_id', $category->id);
		if ($posts->count() > 0) {
			foreach ($posts->cursor() as $post) {
				$post->delete();
			}
		}
		
		// Don't delete the default pictures
		$skin = config('settings.style.skin', 'default');
		$defaultPicture     = 'app/default/categories/fa-folder-' . $skin . '.png';
		$defaultSkinPicture = 'app/categories/' . $skin . '/';
		if (
			!Str::contains($category->picture, $defaultPicture)
			&& !Str::contains($category->picture, $defaultSkinPicture)
			&& $disk->exists($category->picture)
		) {
			$disk->delete($category->picture);
		}
		
		// Delete the category's children recursively
		$this->deleteChildrenRecursively($category);
	}
	
	/**
	 * Listen to the Entry saved event.
	 *
	 * @param Category $category
	 * @return void
	 */
	public function saved(Category $category)
	{
		// Convert Adjacent List to Nested Set
		// $this->adjacentToNestedByItem($category);
		
		// Removing Entries from the Cache
		$this->clearCache($category);
	}
	
	/**
	 * Listen to the Entry deleted event.
	 *
	 * @param Category $category
	 * @return void
	 */
	public function deleted(Category $category)
	{
		// Convert Adjacent List to Nested Set
		// $this->adjacentToNestedByItem($category);
		
		// Removing Entries from the Cache
		$this->clearCache($category);
	}
	
	/**
	 * Removing the Entity's Entries from the Cache
	 *
	 * @param $category
	 * @return void
	 */
	private function clearCache($category)
	{
		try {
			cache()->flush();
		} catch (\Exception $e) {}
	}
}
