/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { setError, setItems, setItemsTotalCount } from './actions';
import { request } from '../utils';
import { BaseQueryParams } from '../types/query-params';
import { ItemType } from './types';

export function* getItems< Query extends BaseQueryParams >(
	itemType: ItemType,
	query: Partial< Query >
) {
	try {
		const endpoint =
			itemType === 'categories' ? 'products/categories' : itemType;
		const { items, totalCount } = yield request(
			`${ NAMESPACE }/${ endpoint }`,
			query
		);

		yield setItemsTotalCount( itemType, query, totalCount );
		yield setItems( itemType, query, items );
	} catch ( error ) {
		yield setError( itemType, query, error );
	}
}

export function* getItemsTotalCount< Query extends BaseQueryParams >(
	itemType: ItemType,
	query: Partial< Query >
) {
	try {
		const totalsQuery = {
			...query,
			page: 1,
			per_page: 1,
		};
		const endpoint =
			itemType === 'categories' ? 'products/categories' : itemType;
		const { totalCount } = yield request(
			`${ NAMESPACE }/${ endpoint }`,
			totalsQuery
		);
		yield setItemsTotalCount( itemType, query, totalCount );
	} catch ( error ) {
		yield setError( itemType, query, error );
	}
}

export function* getReviewsTotalCount< Query extends BaseQueryParams >(
	itemType: ItemType,
	query: Partial< Query >
) {
	yield getItemsTotalCount( itemType, query );
}
