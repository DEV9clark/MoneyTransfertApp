/**
 * DashboardApi - Modernized GraphQL Client
 * Extracted and refactored from legacy BackOffice.js
 */

// Configuration for Entity Fields (formerly listofrequests_assoc)
// Defines exactly which fields to fetch for each entity type.
const ENTITY_SCHEMAS = {
    "banques": "id,nom,description",
    "bureaus": "id,code,nom",
    "clients": "id,name,phone,created_at",
    "users": "id,name,email,created_at",
    "transactions": "id,uuid,amount,currency,status,type,reference,created_at,client{id,name,phone}",
    "wallets": "id,uuid,balance,currency,user{id,name,email}", // Added for Money Transfer App

    // Add other schemas from original file as needed...
};

class DashboardApi {
    constructor(baseUrl = '/') {
        this.baseUrl = baseUrl;
    }

    /**
     * Helper to get the fields definition for a given entity
     */
    getFields(entityName) {
        return ENTITY_SCHEMAS[entityName] || 'id';
    }

    /**
     * Execute a GraphQL Query
     * @param {string} query - The GraphQL query string
     * @param {object} variables - Variables for the query
     */
    async _graphqlRequest(query, variables = {}) {
        const response = await fetch(`${this.baseUrl}graphql`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                query,
                variables
            })
        });

        const result = await response.json();

        if (result.errors) {
            throw new Error(result.errors.map(e => e.message).join(', '));
        }

        return result.data;
    }

    /**
     * Fetch a list of items for an entity
     * @param {string} entity - Entity name (e.g., 'clients')
     * @param {object} filters - Optional filters
     */
    async getItems(entity, filters = null) {
        const fields = this.getFields(entity);
        // Basic query construction - can be expanded for arguments/filters
        // Example: { clients { id, nom ... } }

        let queryArgs = '';
        if (filters) {
            // Convert object {status: 'active'} to string (status: "active")
            // This is a simple implementation, standard GraphQL variables are better.
        }

        const query = `
            query {
                ${entity}${queryArgs} {
                    ${fields}
                }
            }
        `;

        try {
            const data = await this._graphqlRequest(query);
            return data[entity];
        } catch (error) {
            console.error(`Error fetching ${entity}:`, error);
            throw error;
        }
    }

    /**
     * Fetch paginated items
     * @param {string} entity 
     * @param {number} page 
     * @param {number} perPage 
     */
    async getPaginatedItems(entity, page = 1, perPage = 10) {
        const fields = this.getFields(entity);
        const query = `
            query {
                ${entity}(first: ${perPage}, page: ${page}) {
                    data {
                        ${fields}
                    },
                    paginatorInfo {
                        currentPage
                        lastPage
                        total
                    }
                }
            }
        `;

        try {
            const data = await this._graphqlRequest(query);
            return data[entity];
        } catch (error) {
            console.error(`Error fetching paginated ${entity}:`, error);
            throw error;
        }
    }
}

// Export for module usage or attach to window for vanilla JS usage
window.DashboardApi = DashboardApi;
window.ENTITY_SCHEMAS = ENTITY_SCHEMAS;
