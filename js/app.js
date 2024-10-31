/**
 * Demo 5
 */
( function( $ ) {

	var wp = window.wp || {};
	
	wp.pluginsEnabler = {};

    /** Models **/

    // Blog
    wp.pluginsEnabler.Blog = Backbone.Model.extend({

        defaults : {
            id : 0,
            name : ''
        }

    });

    // Plugin
    wp.pluginsEnabler.Plugin = Backbone.Model.extend({

        defaults : {
            id : 0,
            name : '',
            allowed:false,
            blogId:0
        }

    });

    // blog plugins
    wp.pluginsEnabler.blogPlugin = Backbone.Model.extend({

        defaults : {
            id : 0,
            plugins : [],
        }

    });

    /** Collections **/

    // Blogs Collection
    wp.pluginsEnabler.Blogs = Backbone.Collection.extend({

        model : wp.pluginsEnabler.Blog,
        meta: {
            limit:pluginsenabler_strings.limit,
            offset:0
        },

        initialize : function() {
            //this.on( 'sync', this.parse, this );
        },

        sync: function( method, model, options ) {
            if( 'read' === method ) {
                options = options || {};
                options.context = this;
                options.data = _.extend( options.data || {}, {
                    action: 'plugins_enabler_get_blogs',
                    nonce: pluginsenabler_strings._penonce,
                    args: this.meta,
                });
                
                return wp.ajax.send( options );
            }
        },

        parse: function( resp, xhr ) {
            if ( ! _.isArray( resp.blogs ) )
                resp.blogs = [resp.blogs];

            _.each( resp.blogs, function( value, index ) {
                if ( _.isNull( value ) )
                    return;

                resp.blogs[index].registered = new Date( value.registered );
            });

            this.meta = _.defaults( resp.options, { limit:20 } );
            
            return resp.blogs;
        },

    });

    // Plugins Collection
    wp.pluginsEnabler.Plugins = Backbone.Collection.extend({

        model : wp.pluginsEnabler.Plugin,
        blogId:0,

        setBlog:function( blogid ) {
            this.blogId = Number( blogid );
        },

        sync: function( method, model, options ) {

            if( 'read' === method ) {
                options = options || {};
                options.context = this;
                options.data = _.extend( options.data || {}, {
                    action: 'plugins_enabler_get_plugins',
                    nonce: pluginsenabler_strings._penonce,
                    blog_id: this.blogId
                });
                
                return wp.ajax.send( options );
            }

        },

        parse: function( resp, xhr ) {
            var that = this;
            if ( ! _.isArray( resp ) )
                resp = [resp];

            _.each( resp, function( value, index ) {
                if ( _.isNull( value ) )
                    return;

                resp[index].blogId = Number( that.blogId );
            });
            
            return resp;
        },

    });

    // Plugins Collection
    wp.pluginsEnabler.blogPlugins = Backbone.Collection.extend({

        model : wp.pluginsEnabler.blogPlugin,

    });

    /** views **/

    wp.pluginsEnabler.blogView = Backbone.View.extend({
        tagName: 'tr',
        template:  wp.template('blogs-list'),
        className:'alternate',

        render: function() {
            this.$el.html( this.template( this.model.toJSON() ) );
            return this;
        }

    });

    wp.pluginsEnabler.pluginView = wp.pluginsEnabler.blogView.extend({
        template:  wp.template('plugins-list'),
        className:'active',

        events:{
            'click .plugins-item' : 'selectPlugin',
        },

        selectPlugin:function( event ) {
            var allowed = $( event.target ).prop('checked');
            
            //double check !
            if ( this.model.id == event.target.value ) {
                this.model.set( {allowed: allowed} );
            }
        },

    });

    wp.pluginsEnabler.pluginsHeaderView = wp.pluginsEnabler.blogView.extend({
        el: $('#plugins-enabler-blog-header'),
        template:  wp.template('plugins-header'),

        initialize:function(){
            this.render();
            this.model.on( 'change', this.reRender, this );
        },

        reRender:function( model ) {
            this.deactivate();
            this.render();
        },

        deactivate:function() {
             this.$el.html('');
        }
    });

    wp.pluginsEnabler.pluginsFooterView = wp.pluginsEnabler.pluginsHeaderView.extend({
        el: $('#plugins-enabler-plugins-actions'),
        template:  wp.template('plugins-actions'),

        events:{
            "click #save-plugins" : "saveVisibility",
        },

        initialize:function(){
            this.collection.on( 'add', this.render, this );
            this.model.on( 'change', this.reRender, this );
        },

        saveVisibility:function( event ) {
            var button, spinner,
                self = this;

            button = $(event.target);
            button.prop( 'disabled', 'disabled' );
            spinner = button.parent('div').find( '.spinner' );
            spinner.show();

            var model = this.model,
                plugins;

            if( event.target.value != model.id )
                return false;

            _.each( this.collection.models, function( item ){
                if( model.id == item.id )
                    plugins = item.attributes.plugins;
            });

            $('#plugins-enabler-pluginmessage').html('').hide();

            wp.ajax.post( 'plugins_enabler_save_plugins', {
                id:      model.id,
                nonce: pluginsenabler_strings._penonce,
                plugins: plugins
            } ).done(function( success ) {
                spinner.hide();
                $('#plugins-enabler-pluginmessage').append( '<p>'+  success + '</p>' ).show();
                self.trigger( 'pluginsUpdated', {type:'success'});
            }).fail(function( error ) {
                spinner.hide();
                if( -1 == error )
                    error = pluginsenabler_strings.cheating;
                $('#plugins-enabler-pluginmessage').append( '<p>'+  error + '</p>' ).show();
                self.trigger( 'pluginsUpdated', {type:'error'});
            });
            
        }
    });

    wp.pluginsEnabler.preLoader = Backbone.View.extend({
        tagName: 'tr',
        template: wp.template( 'plugins-enabler-loader' ),

        initialize: function( options ) {
            this.model = new Backbone.Model( options );
        },

        render: function() {
            this.$el.html( this.template( this.model.toJSON() ) );
            return this;
        }
    });

    wp.pluginsEnabler.blogsView = Backbone.View.extend({
        el: $('#blogs-list-items'),

        initialize: function() {
            this.loader = new wp.pluginsEnabler.preLoader({
                    colspan:3, 
                    message:pluginsenabler_strings.loadingblogs
            });
            this.classname = 'alternate';

            this.$el.html( this.loader.render().$el );
            this.collection.on( 'add', this.render, this );
        },

        render : function( model ) {
            var that = this,
                blog;

            this.loader.remove();
            classname = this.classname;

            blog = new wp.pluginsEnabler.blogView({ 
                model:model,
                className:classname
            });

            this.$el.append( blog.render().$el );

            this.classname = ( classname == 'alternate' ) ? '' : 'alternate';

            return this;
        }
    });

     wp.pluginsEnabler.pluginsView = Backbone.View.extend({
        el: $('#plugins-enabler-plugins-list'),

        initialize: function() {
            this.loader = new wp.pluginsEnabler.preLoader({
                colspan:3, 
                message:pluginsenabler_strings.loadingplugins
            });

            this.collection.on( 'reset', this.refreshDisplay, this );
            this.collection.on( 'add', this.render, this );

            this.refreshDisplay();
        },

        refreshDisplay: function() {
            this.$el.html( this.loader.render().$el );
        },

        render : function( model ) {
            var classname,
                plugin;

            this.loader.remove();

            classname = model.get('allowed') ? 'active' : 'inactive';
            trid = model.cid;

            plugin = new wp.pluginsEnabler.pluginView({ 
                model:model,
                className:classname,
                id:trid,
            });

            this.$el.append( plugin.render().$el );

            return this;
        }
    });

    wp.pluginsEnabler.App = Backbone.View.extend({
        el: $('#plugins-enabler-main'),
        blogs: {},
        plugins:{},
        views: {},

        events: {
            'click #loadmore-blogs' : 'loadmoreBlogs',
        },

        initialize: function() {
            // Collections
            this.blogs = new wp.pluginsEnabler.Blogs();
            this.plugins = new wp.pluginsEnabler.Plugins();
            this.collection = new wp.pluginsEnabler.blogPlugins();

            //Router Events
            this.on( 'managePlugins', this.managePlugins );
            this.on( 'mainBlogs', this.listBlogs );

            // Plugin Events
            this.plugins.on( 'add', this.initVisibles, this );
            this.plugins.on( 'change', this.refreshVisibles, this );
            this.plugins.on( 'error', this.handlePluginError, this );

            // blog events ?
            this.blogs.on( 'sync', this.maybePaginate, this );
            this.blogs.on( 'error', this.handleBlogError, this );

            // Go!
            this.displayBlogs();
        },        

        initVisibles:function( model ) {
            blogid = model.get('blogId');
            allowed = model.get('allowed');
            plugin = model.get( 'id' );
            blogPlugins = this.collection.get( blogid );


            $('#save-plugins').prop('disabled', true );

            if( blogPlugins ) {
                plugins = blogPlugins.get('plugins');
                if( allowed && -1 == _.indexOf( plugins, plugin ) )
                    plugins.push( plugin );
                blogPlugins.set({
                    plugins:plugins
                });
            } else {
                plugins = allowed ? [plugin] : [];
                this.collection.add({
                    id:blogid,
                    plugins:plugins
                });
            }

        },

        refreshVisibles:function( model ) {
            blogid = model.get( 'blogId' );
            plugin = model.get('id');
            allowed = model.get('allowed');
            blogPlugins = this.collection.get( blogid );
            plugins = blogPlugins.get('plugins');

            $('#save-plugins').prop('disabled', false );

            if( -1 == _.indexOf( plugins, plugin ) && allowed ) {
                plugins.push( plugin );
                blogPlugins.set({plugins:plugins});
            } else if( -1 != _.indexOf( plugins, plugin ) && !allowed ) {
                plugins = _.without( plugins, plugin );
                blogPlugins.set({plugins:plugins});
            }
        },

        managePlugins:function( id ) {
            var model = this.blogs.get( id );

            $('#plugins-enabler-pluginmessage').html('').hide();

            this.toggleView( 'plugins' );
            // header
            this.pluginsHeader( model )
            // content
            this.displayPlugins( model );
            // footer
            this.pluginsFooter( model );
        },

        pluginsHeader:function( model ) {
            headerModel = new Backbone.Model( model.attributes );

            if( _.isUndefined( this.views.pluginsHeader ) ) {
                this.views.pluginsHeader = new wp.pluginsEnabler.pluginsHeaderView({
                    model:headerModel
                });
            } else {
                this.views.pluginsHeader.model.set( headerModel.toJSON() );
            }
        },

        pluginsFooter:function( model ) {
            footerModel = new Backbone.Model( model.attributes );

            if( _.isUndefined( this.views.pluginsFooter ) ) {
                this.views.pluginsFooter = new wp.pluginsEnabler.pluginsFooterView({
                   collection:this.collection,
                   model:footerModel
                });
            }
            this.views.pluginsFooter.on( 'pluginsUpdated', this.checkUpdates, this );
            this.views.pluginsFooter.model.set( footerModel.toJSON() );
        },

        checkUpdates:function( result ) {
            var self = this;
            if( result.type == 'success' ){
                _.each( this.plugins.models, function( model ){
                    if( model.hasChanged( 'allowed') ) {
                        tr = self.views.pluginsView.$el.find('#'+model.cid );
                        if( model.get('allowed') )
                            tr.removeClass('inactive').addClass('active');
                        else
                            tr.removeClass('active').addClass('inactive');
                    }
                });
            } else {
                //we need to put back the values !
                _.each( this.plugins.models, function( model ){
                    if( model.hasChanged( 'allowed') ) {
                        tr = self.views.pluginsView.$el.find('#'+model.cid );
                        cb = tr.find('.plugins-item');
                        previousAllowed = model.previous('allowed');
                        if( previousAllowed ){
                            tr.removeClass('inactive').addClass('active');
                            cb.prop('checked', 'checked');
                        } else {
                            tr.removeClass('active').addClass('inactive');
                            cb.prop('checked', false );
                        }
                        model.set( { allowed: previousAllowed } );
                    }
                });
            }
        },

        listBlogs:function() {
            this.toggleView( 'blogs' );
        },

        displayBlogs: function() {
            this.views.blogsView = new wp.pluginsEnabler.blogsView({
                collection: this.blogs
            });
            this.blogs.fetch();
        },

        maybePaginate:function( blogs ) {
            $('#blogs-list-loadmore .spinner').hide();
            // if every blog has loaded stop displaying the button..
            if( blogs.length == blogs.meta.total )
                $('#blogs-list-loadmore').hide();
            else {
                $('#blogs-list-loadmore').show();
                $('#loadmore-blogs').prop( 'disabled', false );
            }
        },

        loadmoreBlogs:function( event ) {
            this.blogs.meta.offset += this.blogs.meta.limit;

            $('#loadmore-blogs').prop( 'disabled', 'disabled' );
            $('#blogs-list-loadmore .spinner').show();

            //double check !
            if( this.blogs.count == this.blogs.meta.total )
                $('#blogs-list-loadmore').hide();
            else
                this.blogs.fetch( {remove: false} );
                
        },

        displayPlugins:function( model ) {

            this.plugins.setBlog( model.id );
            
            if( _.isUndefined( this.views.pluginsView ) ) {
                this.views.pluginsView = new wp.pluginsEnabler.pluginsView({
                    collection: this.plugins
                });

            } else {
                this.plugins.reset();
            }
            this.plugins.fetch();
        },

        toggleView:function( view ) {
            if( 'plugins' == view ) {
                $('#plugins-enabler-blogs').hide();
                $('#plugins-enabler-blog-plugins').show();
            } else {
                $('#plugins-enabler-blogs').show();
                $('#plugins-enabler-blog-plugins').hide();
            }
        },

        handleBlogError: function( model, error ) {
            $('#loadmore-blogs').remove();
            if( -1 == error )
                error = pluginsenabler_strings.cheating;
            $('#plugins-enabler-blogmessage').html( '<p>'+error+'</p>' ).show();
        },

        handlePluginError: function( model, error ) {
            if( -1 == error )
                error = pluginsenabler_strings.cheating;
            $('#plugins-enabler-plugins-list tr td:first').html( '<div id="plugins-enabler-pluginmessage"><p>'+error+'</p></div>' );
            $('#plugins-enabler-pluginmessage').show();
        },
    });

    wp.pluginsEnabler.Router = Backbone.Router.extend({

        routes:{
            '':'main',
            'blog/:id':'blog'
        },

        initialize : function() {
            this.blogList = new wp.pluginsEnabler.App();
        },

        main:function(){
            this.blogList.trigger( 'mainBlogs' );
        },

        blog:function( id ){

            if( this.blogList.blogs.length == 0 )
                this.navigate( '' );
            else
                this.blogList.trigger( 'managePlugins', id );
        }

    });

    wp.pluginsEnabler.router = new wp.pluginsEnabler.Router();

    Backbone.history.start();


} )( jQuery );
