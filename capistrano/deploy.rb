set :application, 'tdfarchive'
set :repo_url, 'git@github.com:colintdf/tdfarchive.git'

set :ssh_options, {:forward_agent => true}
set :use_sudo, false

set :keep_releases, 3

def get_branch()
	branch = ENV.fetch('BRANCH', false)
	# Select current checkout branch if we use BRANCH=.
	if branch === '.'
		proc {`git rev-parse --abbrev-ref HEAD`.chomp}
	elsif branch
		branch
	end
end

set :branch, get_branch() || 'master'
set :linked_dirs, %w{htdocs/wp-content/uploads htdocs/wp-content/webp-express htdocs/wp-content/uploads-webpc htdocs/wp-content/languages spotifycache}

#set :linked_dirs, %w{backups htdocs/.well-known htdocs/wp-content/uploads htdocs/wp-content/cache htdocs/wp-content/mmr}
#set :linked_files, %w{ }

set :format, :airbrussh
set :log_level, :error

set :local_dev_url, 'http://tdf.croftsoftsoftware.com'


namespace :deploy do
	# NB: This action will only occur on the nodes which have mapped network drives (:no_release).
	desc 'Deliberately list files in the network drive. This makes the new "current" capistrano symlink work.'
	task :refresh_network_drive do
		# roles(:nginx, select: :no_release) @see: https://capistranorb.com/documentation/advanced-features/property-filtering/
		on roles(:nginx, select: :no_release) do
			execute "ls -la #{deploy_to}"
		end
	end 
	before :finished, 'deploy:refresh_network_drive'
end

after 'deploy:finished', 'php:reload'
after 'deploy:finished', 'nginx:reload'
after 'deploy:finished', 'nginx:flush'
