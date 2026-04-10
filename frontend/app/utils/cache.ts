/**
 * Generic TTL cache utilities for Pinia stores.
 *
 * Each store wraps a `CacheEntry<T>` in a `ref()` — the ref provides
 * Vue reactivity while these helpers handle expiry logic.
 *
 * @module utils/cache
 */

/** A timestamped cache entry with a configurable TTL. */
export interface CacheEntry<T> {
  data: T
  fetchedAt: number
  ttl: number
}

/** Returns `true` when the entry exists and has not yet expired. */
export function isCacheValid<T>(entry: CacheEntry<T> | null): entry is CacheEntry<T> {
  if (!entry) return false
  return Date.now() - entry.fetchedAt < entry.ttl
}

/** Wrap data in a new cache entry stamped with the current time. */
export function createCacheEntry<T>(data: T, ttl: number): CacheEntry<T> {
  return { data, fetchedAt: Date.now(), ttl }
}
