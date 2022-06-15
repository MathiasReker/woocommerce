/**
 * External dependencies
 */
import createSelector from 'rememo';

/**
 * Internal dependencies
 */
import { getResourceName } from '../utils';
import { getTotalCountResourceName } from './utils';
import { BaseQueryParams } from '../types/query-params';
import { ItemType, ItemsState } from './types';

export const getItems = createSelector(
	( state, itemType, query, defaultValue = new Map() ) => {
		const resourceName = getResourceName( itemType, query );
		const ids =
			state.items[ resourceName ] && state.items[ resourceName ].data;
		if ( ! ids ) {
			return defaultValue;
		}
		return ids.reduce( ( map: Map< string, unknown >, id: string ) => {
			map.set( id, state.data[ itemType ][ id ] );
			return map;
		}, new Map() );
	},
	( state, itemType, query ) => {
		const resourceName = getResourceName( itemType, query );
		return [ state.items[ resourceName ] ];
	}
);

export const getItemsTotalCount = < Query extends BaseQueryParams >(
	state: ItemsState,
	itemType: ItemType,
	query: Query,
	defaultValue = 0
) => {
	const resourceName = getTotalCountResourceName( itemType, query );
	const totalCount = state.items.hasOwnProperty( resourceName )
		? state.items[ resourceName ]
		: defaultValue;
	return totalCount;
};

export const getItemsError = < Query extends BaseQueryParams >(
	state: ItemsState,
	itemType: ItemType,
	query: Query
) => {
	const resourceName = getResourceName( itemType, query );
	return state.errors[ resourceName ];
};
