import React from 'react';
import ReactDOM from 'react-dom';
import { Voyager } from 'graphql-voyager';
import Drupal from 'drupal';
import jQuery from 'jquery';
import once from '@drupal/once';

/**
 * Behavior for rendering the GraphQL Voyager interface.
 */
Drupal.behaviors.graphQLRenderVoyager = {
  attach: (context, settings) => {
    const container = jQuery(once('graphql-voyager', '#graphql-voyager', context))[0] || undefined;

    if (typeof container === 'undefined') {
      return;
    }

    ReactDOM.render(<Voyager
      introspection={settings.graphqlIntrospectionData}
      displayOptions={{ skipRelay: true, sortByAlphabet: true }}
    />, container);
  },
};
