type Link = {
	href: string;
};

export type ItemType = 'categories' | 'products' | 'customers';

export type CategoryItem = {
	count: number;
	description: string;
	display: string;
	id: number;
	image: string | null;
	menu_order: number;
	name: string;
	parent: number;
	slug: string;
	_links: {
		collection: Array< Link >;
		self: Array< Link >;
	};
};

export type ProductItem = {
	attributes: Array< {
		id: number;
		name: string;
		position: number;
		visible: boolean;
		variation: boolean;
		options: string[];
	} >;
	average_rating: string;
	backordered: boolean;
	backorders: string;
	backorders_allowed: boolean;
	button_text: string;
	catalog_visibility: string;
	categories: Array< {
		id: number;
		name: string;
		slug: string;
	} >;
	cross_sell_ids: number[];
	date_created: string;
	date_created_gmt: string;
	date_modified: string;
	date_modified_gmt: string;
	date_on_sale_from: null | string;
	date_on_sale_from_gmt: null | string;
	date_on_sale_to: null | string;
	date_on_sale_to_gmt: null | string;
	default_attributes: Array< {
		id: number;
		name: string;
		option: string;
	} >;
	description: string;
	dimensions: { length: string; width: string; height: string };
	download_expiry: number;
	download_limit: number;
	downloadable: boolean;
	downloads: Array< {
		id: number;
		name: string;
		file: string;
	} >;
	external_url: string;
	featured: boolean;
	grouped_products: Array< number >;
	has_options: boolean;
	id: number;
	images: Array< {
		id: number;
		date_created: string;
		date_created_gmt: string;
		date_modified: string;
		date_modified_gmt: string;
		src: string;
		name: string;
		alt: string;
	} >;
	low_stock_amount: null | number;
	manage_stock: boolean;
	menu_order: number;
	meta_data: Array< {
		id: number;
		key: string;
		value: string;
	} >;
	name: string;
	on_sale: boolean;
	parent_id: number;
	permalink: string;
	price: string;
	price_html: string;
	purchasable: boolean;
	purchase_note: string;
	rating_count: number;
	regular_price: string;
	related_ids: number[];
	reviews_allowed: boolean;
	sale_price: string;
	shipping_class: string;
	shipping_class_id: number;
	shipping_required: boolean;
	shipping_taxable: boolean;
	short_description: string;
	sku: string;
	slug: string;
	sold_individually: boolean;
	status: string;
	stock_quantity: number;
	stock_status: string;
	tags: Array< {
		id: number;
		name: string;
		slug: string;
	} >;
	tax_class: string;
	tax_status: string;
	total_sales: number;
	type: string;
	upsell_ids: number[];
	variations: Array< {
		id: number;
		date_created: string;
		date_created_gmt: string;
		date_modified: string;
		date_modified_gmt: string;
		attributes: Array< {
			id: number;
			name: string;
			option: string;
		} >;
		image: string;
		price: string;
		regular_price: string;
		sale_price: string;
		sku: string;
		stock_quantity: number;
		tax_class: string;
		tax_status: string;
		total_sales: number;
		weight: string;
	} >;
	virtual: boolean;
	weight: string;
};

export type CustomerItem = {
	id: number;
	date_registered: string;
	date_registered_gmt: string;
	country: string;
	name: string;
	user_id: number;
	email: string;
	username: string;
	state: string;
	city: string;
	date_last_active: string;
	date_last_active_gmt: string;
	orders_count: number;
	total_spent: number;
	avg_order_value: number;
	_links: {
		self: Array< Link >;
	};
};

export type Item = Partial< CategoryItem | ProductItem | CustomerItem > & {
	id: number;
};

export type ItemsState = {
	items:
		| Record< string, { data: number[] } | number >
		| Record< string, never >;
	data: Partial< Record< ItemType, { [ id: number ]: Item } > >;
	errors: {
		[ key: string ]: unknown;
	};
};
