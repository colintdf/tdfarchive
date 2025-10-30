namespace :release do
    desc 'Create release information'
	task :info do
		stage = fetch(:stage)
		on release_roles(:all) do
            execute "echo { branch:#{fetch(:branch)}, env:#{stage}, timestamp:#{release_timestamp} } > #{shared_path}/releaseinfo/branch.inf"
		end
	end
end
