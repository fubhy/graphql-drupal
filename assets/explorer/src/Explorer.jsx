import React, { useState } from 'react';
import { GraphiQL } from 'graphiql';
import { explorerPlugin } from '@graphiql/plugin-explorer';

const Explorer = ({
  fetcher, schema, query: q, variables,
}) => {
  const [query, setQuery] = useState(q);

  const explorer = explorerPlugin();

  return (
    <div className="graphiql-container">
      <GraphiQL
        fetcher={fetcher}
        plugins={[explorer]}
        schema={schema}
        query={query}
        variables={variables}
        onEditQuery={setQuery}
      >
        <GraphiQL.Logo>
          <a
            className="graphiql-logo-link"
            href="https://www.drupal.org/project/graphql"
            target="_blank"
            rel="noreferrer"
          >
            GraphQL
          </a>
        </GraphiQL.Logo>
      </GraphiQL>
    </div>
  );
};

export default Explorer;
